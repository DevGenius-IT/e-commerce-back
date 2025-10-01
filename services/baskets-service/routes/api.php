<?php

use App\Http\Controllers\API\BasketController;
use App\Http\Controllers\API\PromoCodeController;
use App\Http\Controllers\API\TypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check route
Route::get('/health', function () {
    return response()->json([
        'service' => 'baskets-service',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});

// Public routes for promo code validation
Route::post('/promo-codes/validate', [PromoCodeController::class, 'validate']);

// Protected routes requiring JWT authentication
Route::middleware([\Shared\Middleware\JWTAuthMiddleware::class])->group(function () {
    
    // Basket routes for authenticated users
    Route::prefix('baskets')->group(function () {
        Route::get('/current', [BasketController::class, 'current']);
        Route::post('/items', [BasketController::class, 'addItem']);
        Route::put('/items/{id}', [BasketController::class, 'updateItem']);
        Route::delete('/items/{id}', [BasketController::class, 'removeItem']);
        Route::post('/promo-codes', [BasketController::class, 'applyPromoCode']);
        Route::delete('/promo-codes/{id}', [BasketController::class, 'removePromoCode']);
        Route::delete('/clear', [BasketController::class, 'clear']);
    });
    
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        
        // Basket management (Admin)
        Route::prefix('admin/baskets')->group(function () {
            Route::get('/', [BasketController::class, 'index']);
            Route::get('/{id}', [BasketController::class, 'show']);
            Route::delete('/{id}', [BasketController::class, 'destroy']);
        });
        
        // Promo Code management (Admin)
        Route::prefix('admin/promo-codes')->group(function () {
            Route::get('/', [PromoCodeController::class, 'index']);
            Route::post('/', [PromoCodeController::class, 'store']);
            Route::get('/{id}', [PromoCodeController::class, 'show']);
            Route::put('/{id}', [PromoCodeController::class, 'update']);
            Route::delete('/{id}', [PromoCodeController::class, 'destroy']);
        });
        
        // Type management (Admin)
        Route::prefix('admin/types')->group(function () {
            Route::get('/', [TypeController::class, 'index']);
            Route::post('/', [TypeController::class, 'store']);
            Route::get('/{id}', [TypeController::class, 'show']);
            Route::put('/{id}', [TypeController::class, 'update']);
            Route::delete('/{id}', [TypeController::class, 'destroy']);
        });
    });
});

// Fallback route
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint not found'
    ], 404);
});