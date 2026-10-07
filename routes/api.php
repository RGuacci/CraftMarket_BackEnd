<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
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
    Route::get('/seller/products', [ProductController::class, 'myProducts']); 
   
    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('can:create,' . Product::class);

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('can:update,product');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('can:delete,product');
});
