<?php

namespace App\Http\Controllers;

use App\Http\Resources\CartResource;
use App\Models\Product;
use Illuminate\Http\Request;


class CartController extends Controller
{    
    // Index
    public function index(Request $request)
{
    $cart = $request->user()->cart()
        ->with([
            'products.user',
            'products.categories',
            'products.images',
        ])
        ->first();

    if (!$cart) {
        return response()->json([
            'data' => [
                'id' => null,
                'items' => [],
            ],
        ]);
    }

    return new CartResource($cart);
}

    // Create
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $quantity = $validated['quantity'] ?? 1;

        if ($product->stock < $quantity) {
            return response()->json([
                'message' => 'Quantità non disponibile.',
            ], 422);
        }

        $cart = $request->user()->cart()->firstOrCreate([]);

        $existingQuantity = $cart->products()
            ->where('products.id', $product->id)
            ->first()
            ?->pivot->quantity ?? 0;

        if ($existingQuantity + $quantity > $product->stock) {
            return response()->json([
                'message' => 'Stock insufficiente per questa quantità.',
            ], 422);
        }

        $cart->products()->syncWithoutDetaching([
            $product->id => [
                'quantity' => $existingQuantity + $quantity,
            ],
        ]);

        $cart->load([
            'products.user',
            'products.categories',
            'products.images',
        ]);

        return response()->json([
            'message' => 'Prodotto aggiunto al carrello.',
            'cart' => new CartResource($cart),
        ], 201);
    }

    // Update

    public function update(Request $request, int $productId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0'
        ]);

        $quantity = $data['quantity'];

        $product = Product::findOrFail($productId);

        $cart = $request->user()->cart;
        
        if(!$cart){
            return response()->json(['message' => 'Carrello non trovato'] ,404);
        }

        $item = $cart->products()
        ->where('products.id', $product->id)
        ->first();

        if(!$item){
            return response()->json([
                'message' => 'Articolo non presente nel carrello'
            ], 404);
        }

        if($quantity > $product->stock){
            return response()->json([
                'message' => 'Quantità superiore al numero di articoli disponibili'
            ], 422);
        }

        if($quantity === 0){
            $cart->products()->detach($product->id);
        }else{
            $cart->products()->updateexistingPivot(
                $product->id,
                ['quantity' => $quantity]
            );
        }

        $cart->load(['products.user', 'products.categories', 'products.images']);

        return response()->json([
            'message' => $quantity === 0
              ? 'Prodotto rimosso dal carrello.'
              : 'Quantità aggiornata',
            'cart' => new CartResource($cart),
        ]);
    }
}