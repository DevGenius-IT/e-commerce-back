<?php

use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\CategoryController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'products-service']);
});

// Public product routes (for catalog browsing)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('featured', [ProductController::class, 'featured']);
    Route::get('search', [ProductController::class, 'search']);
    Route::get('{product}', [ProductController::class, 'show']);
});

// Public category routes
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('tree', [CategoryController::class, 'tree']);
    Route::get('{category}', [CategoryController::class, 'show']);
    Route::get('{category}/products', [CategoryController::class, 'products']);
    Route::get('{category}/breadcrumb', [CategoryController::class, 'breadcrumb']);
});

// Admin routes for product management (protected by gateway)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    // Product management
    Route::prefix('products')->group(function () {
        Route::post('/', [ProductController::class, 'store']);
        Route::put('{product}', [ProductController::class, 'update']);
        Route::patch('{product}', [ProductController::class, 'update']);
        Route::delete('{product}', [ProductController::class, 'destroy']);
        Route::post('{product}/stock', [ProductController::class, 'updateStock']);
    });

    // Category management
    Route::prefix('categories')->group(function () {
        Route::post('/', [CategoryController::class, 'store']);
        Route::put('{category}', [CategoryController::class, 'update']);
        Route::patch('{category}', [CategoryController::class, 'update']);
        Route::delete('{category}', [CategoryController::class, 'destroy']);
    });
});