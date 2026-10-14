<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function checkout(
        Request $request,
        OrderService $orderService
    ) {
        $validated = $request->validate([
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_postal_code' => ['required', 'string', 'max:20'],
            'shipping_country' => ['required', 'string', 'max:100'],
        ]);

        $order = $orderService->createPendingOrder(
            $request->user(),
            $validated
        );

        return response()->json([
            'message' => 'Ordine creato.',
            'order' => $order,
        ], 201);
    }
    
    public function pay(
        Request $request,
        Order $order,
        OrderService $orderService
    ) {
        $validated = $request->validate([
            'method' => ['required', 'in:card,paypal'],
        ]);

        $updatedOrder = $orderService->processPayment(
            $request->user(),
            $order,
            $validated['method']
        );

        return response()->json([
            'message' => 'Pagamento elaborato.',
            'order' => $updatedOrder,
        ]);
    }
}
