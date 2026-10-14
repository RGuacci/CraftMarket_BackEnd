<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Visualizzazione pubblica
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

// Operazioni riservate
Route::middleware('auth:sanctum')->group(function () {
    // prodotti
    Route::get('/seller/products', [ProductController::class, 'myProducts']);

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:create,' . Product::class);

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:update,product');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('can:delete,product');

    //  carrello
    Route::get('/cart', [CartController::class, 'index']);

    Route::post('/cart/products', [CartController::class, 'store']);

    Route::patch('/cart/products/{productId}', [CartController::class, 'update']);

    // Ordini
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // pagamento simulato   
    Route::post('/checkout', [OrderController::class, 'checkout']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);
});
