<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createPendingOrder(User $user, array $shippingData): Order
    {
        return DB::transaction(function () use ($user, $shippingData) {
            // Ordini in attesa precedenti: non hanno scalato stock né incassato nulla.
            // Sta dentro la transazione: se più sotto la validazione fallisce, la cancellazione viene annullata.
            Order::where('user_id', $user->id)->where('status', 'pending')->delete();

            $cart = $user->cart()->first();

            // withTrashed() include anche i prodotti eliminati, così posso segnalarli
            $products = $cart ? $cart->products()->withTrashed()->get() : collect();

            if ($products->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Il carrello è vuoto.']);
            }

            $cents = 0;

            foreach ($products as $product) {
                if ($product->trashed()) {
                    throw ValidationException::withMessages([
                        'cart' => "Il prodotto {$product->name} non è più disponibile. Rimuovilo dal carrello.",
                    ]);
                }

                $quantity = (int) $product->pivot->quantity;

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insufficiente per {$product->name}.",
                    ]);
                }

                // Calcolo in centesimi interi: niente errori di arrotondamento
                $cents += (int) round($product->price * 100) * $quantity;
            }

            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total' => $cents / 100,
                'shipping_name' => $shippingData['shipping_name'],
                'shipping_address' => $shippingData['shipping_address'],
                'shipping_city' => $shippingData['shipping_city'],
                'shipping_postal_code' => $shippingData['shipping_postal_code'],
                'shipping_country' => $shippingData['shipping_country'],
            ]);

            foreach ($products as $product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => (int) $product->pivot->quantity,
                    'price' => $product->price,
                ]);
            }

            return $order->load('orderItems');
        });
    }


    public function processPayment(
        User $user,
        Order $order,
        string $method,
    ): Order {
        return DB::transaction(function () use ($user, $order, $method) {
            $order = Order::whereKey($order->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($order->status, ['pending', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Questo ordine non può essere pagato.',
                ]);
            }

            $order->load('orderItems');

            foreach ($order->orderItems as $item) {
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insufficiente per il prodotto {$item->product_id}.",
                    ]);
                }
            }

            $paymentService = app(PaymentService::class);
            $payment = $paymentService->process($method);

            if ($payment['status'] !== 'succeeded') {
                $order->update(['status' => 'failed']);

                return $order->refresh()->load('orderItems');
            }

            foreach ($order->orderItems as $item) {
                Product::whereKey($item->product_id)
                    ->decrement('stock', $item->quantity);
            }

            $order->update(['status' => 'paid']);

            $productIds = $order->orderItems
                ->pluck('product_id')
                ->all();

            $order->user->cart?->products()->detach($productIds);

            return $order->refresh()->load('orderItems');
        });
    }
}
