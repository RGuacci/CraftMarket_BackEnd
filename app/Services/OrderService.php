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
