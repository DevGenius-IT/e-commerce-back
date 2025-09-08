<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\QueueController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api')->group(function () {
    // Health check
    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'service' => 'messages-broker',
            'timestamp' => now()
        ]);
    });

    // Message routes
    Route::prefix('messages')->group(function () {
        Route::post('/publish', [MessageController::class, 'publish']);
        Route::get('/failed', [MessageController::class, 'failed']);
        Route::post('/retry/{id}', [MessageController::class, 'retry']);
        Route::delete('/failed/{id}', [MessageController::class, 'deleteFailed']);
    });

    // Queue management routes
    Route::prefix('queues')->group(function () {
        Route::get('/', [QueueController::class, 'index']);
        Route::get('/{queue}/stats', [QueueController::class, 'stats']);
        Route::post('/{queue}/purge', [QueueController::class, 'purge']);
    });
});