<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function checkout(Request $request, OrderService $orderService)
    {
        $user = $request->user();

        $order = $orderService->createPendingOrder($user);

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
