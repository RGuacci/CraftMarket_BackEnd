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
        // Recupero il carrello
        return DB::transaction(function () use ($user, $shippingData) {
            $cart = $user->cart()
                ->with('products')
                ->first();

            if (!$cart || $cart->products->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Il carrello è vuoto.',
                ]);
            }

            // Verifico che lo stock del prodotto sia sufficiente
            $total = 0;

            foreach ($cart->products as $product) {
                $quantity = (int) $product->pivot->quantity;

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insufficiente per {$product->name}.",
                    ]);
                }

                $total += $product->price * $quantity;
            }

            // Creo l'ordine
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
                'total' => $total,
                'shipping_name' => $shippingData['shipping_name'],
                'shipping_address' => $shippingData['shipping_address'],
                'shipping_city' => $shippingData['shipping_city'],
                'shipping_postal_code' => $shippingData['shipping_postal_code'],
                'shipping_country' => $shippingData['shipping_country'],
            ]);

            // Riempio la tabella order_item
            foreach ($cart->products as $product) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $product->pivot->quantity,
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

        if ($order->status !== 'pending') {
            throw ValidationException::withMessages([
                'order' => 'Questo ordine non può essere pagato.',
            ]);
        }

        $paymentService = app(PaymentService::class);

        $payment = $paymentService->process($method);

        if ($payment['status'] === 'failed') {
            $order->update(['status' => 'failed']);

            return $order->refresh();
        }

        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'order' => 'Questo ordine non può essere pagato.',
                ]);
            }

            foreach ($order->orderItems as $item) {
                $product = Product::whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || $product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'stock' => "Stock insufficiente per il prodotto {$item->product_id}.",
                    ]);
                }

                $product->decrement('stock', $item->quantity);
            }

            $order->update(['status' => 'paid']);
            // Rimuovo dal carrello soltanto i prodotti acquistati
            $productIds = $order->orderItems
                ->pluck('product_id')
                ->all();

            $order->user->cart?->products()->detach($productIds);

            return $order->refresh()->load('orderItems');
        });
    }
}
