<?php

use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\OrderStatusController;
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
        'service' => 'orders-service',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});

// Debug route
Route::get('/debug', function () {
    return "Simple debug text";
});

// Public routes for order status information
Route::prefix('order-status')->group(function () {
    Route::get('/', [OrderStatusController::class, 'index']);
    Route::get('/{id}', [OrderStatusController::class, 'show']);
    Route::get('/statistics', [OrderStatusController::class, 'statistics']);
});

// Protected routes requiring JWT authentication
Route::middleware([\Shared\Middleware\JWTAuthMiddleware::class])->group(function () {
    
    // User order routes
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/create-from-basket', [OrderController::class, 'createFromBasket']);
        Route::put('/{id}/status', [OrderController::class, 'updateStatus']);
        Route::put('/{id}/cancel', [OrderController::class, 'cancel']);
    });
    
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        
        // Order management (Admin)
        Route::prefix('admin/orders')->group(function () {
            Route::get('/', [OrderController::class, 'adminIndex']);
            Route::get('/{id}', [OrderController::class, 'adminShow']);
            Route::put('/{id}', [OrderController::class, 'adminUpdate']);
            Route::delete('/{id}', [OrderController::class, 'adminDestroy']);
        });
        
        // Order Status management (Admin)
        Route::prefix('admin/order-status')->group(function () {
            Route::post('/', [OrderStatusController::class, 'store']);
            Route::put('/{id}', [OrderStatusController::class, 'update']);
            Route::delete('/{id}', [OrderStatusController::class, 'destroy']);
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