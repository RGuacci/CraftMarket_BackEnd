<?php

use App\Http\Controllers\Api\ProductController;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// Visualizzazione pubblica
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// Operazioni riservate
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:create,' . Product::class);

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:update,product');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('can:delete,product');
});
