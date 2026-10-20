<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SellerRequestController;
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
        ->middleware('can:create,' . Product::class, 'throttle:uploads');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:update,product', 'throttle:uploads');

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
    Route::post('/checkout', [OrderController::class, 'checkout'])
        ->middleware('throttle:orders');

    Route::get('/orders/{order}', [OrderController::class, 'show'])
        ->middleware('can:view,order');

    Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])
        ->middleware('can:pay,order', 'throttle:orders');

    //Rotta per invio candidatura
    Route::post('/seller-requests', [SellerRequestController::class, 'store']);

    //Rotta per la visualizzazione di una candidatura
    Route::get('/seller-requests/me', [SellerRequestController::class, 'myRequest',]);

    //Rotte admin per gestire candidature
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/seller-requests', [SellerRequestController::class, 'index']);
    });

    Route::patch('/seller-requests/{sellerRequest}/approve', [
        SellerRequestController::class,
        'approve',
    ]);

    Route::patch('/seller-requests/{sellerRequest}/reject', [
        SellerRequestController::class,
        'reject',
    ]);
});
