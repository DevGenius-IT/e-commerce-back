<?php

use App\Http\Controllers\API\DeliveryController;
use App\Http\Controllers\API\SalePointController;
use App\Http\Controllers\API\StatusController;
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
        'service' => 'deliveries-service',
        'status' => 'healthy',
        'timestamp' => now()
    ]);
});

// Debug route
Route::get('/debug', function () {
    return "Deliveries service debug endpoint";
});

// Public routes for delivery tracking
Route::prefix('deliveries')->group(function () {
    Route::get('/track/{trackingNumber}', [DeliveryController::class, 'track']);
});

// Public routes for sale points
Route::prefix('sale-points')->group(function () {
    Route::get('/', [SalePointController::class, 'index']);
    Route::get('/{id}', [SalePointController::class, 'show']);
    Route::get('/nearby', [SalePointController::class, 'nearby']);
});

// Public routes for status information
Route::prefix('status')->group(function () {
    Route::get('/', [StatusController::class, 'index']);
    Route::get('/{id}', [StatusController::class, 'show']);
    Route::get('/statistics', [StatusController::class, 'statistics']);
});

// Protected routes requiring JWT authentication
Route::middleware(['jwt.auth'])->group(function () {
    
    // User delivery routes
    Route::prefix('deliveries')->group(function () {
        Route::get('/', [DeliveryController::class, 'index']);
        Route::get('/{id}', [DeliveryController::class, 'show']);
        Route::put('/{id}/status', [DeliveryController::class, 'updateStatus']);
        Route::post('/from-order', [DeliveryController::class, 'createFromOrder']);
    });
    
    // Admin-only routes
    Route::middleware('admin')->group(function () {
        
        // Delivery management (Admin)
        Route::prefix('admin/deliveries')->group(function () {
            Route::get('/', [DeliveryController::class, 'index']);
            Route::get('/statistics', [DeliveryController::class, 'statistics']);
            Route::post('/', [DeliveryController::class, 'store']);
            Route::get('/{id}', [DeliveryController::class, 'show']);
            Route::put('/{id}', [DeliveryController::class, 'update']);
            Route::delete('/{id}', [DeliveryController::class, 'destroy']);
        });
        
        // Sale point management (Admin)
        Route::prefix('admin/sale-points')->group(function () {
            Route::get('/', [SalePointController::class, 'index']);
            Route::get('/statistics', [SalePointController::class, 'statistics']);
            Route::post('/', [SalePointController::class, 'store']);
            Route::get('/{id}', [SalePointController::class, 'show']);
            Route::put('/{id}', [SalePointController::class, 'update']);
            Route::delete('/{id}', [SalePointController::class, 'destroy']);
        });
        
        // Status management (Admin)
        Route::prefix('admin/status')->group(function () {
            Route::get('/', [StatusController::class, 'index']);
            Route::get('/statistics', [StatusController::class, 'statistics']);
            Route::post('/', [StatusController::class, 'store']);
            Route::get('/{id}', [StatusController::class, 'show']);
            Route::put('/{id}', [StatusController::class, 'update']);
            Route::delete('/{id}', [StatusController::class, 'destroy']);
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