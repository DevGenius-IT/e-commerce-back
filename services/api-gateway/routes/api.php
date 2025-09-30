<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GatewayController;
use Illuminate\Support\Facades\Route;

Route::group([], function () {
    // Health check endpoint
    Route::get('health', function () {
        return response()->json(['status' => 'healthy', 'service' => 'api-gateway']);
    });

    // Test RabbitMQ connection
    Route::get('test-rabbitmq', function () {
        try {
            $rabbitMQClient = new \Shared\Services\RabbitMQClientService();
            $rabbitMQClient->connect();
            $isConnected = $rabbitMQClient->isConnected();
            return response()->json([
                'status' => 'success',
                'rabbitmq_connected' => $isConnected,
                'message' => $isConnected ? 'RabbitMQ connection successful' : 'RabbitMQ not connected'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'RabbitMQ connection failed: ' . $e->getMessage()
            ], 500);
        }
    });

    // V1 API routes (when accessed via /v1/ from Nginx)
    Route::prefix('v1')->group(function () {
        // Service status endpoint
        Route::get('services/status', function (GatewayController $gateway) {
            $router = new \App\Services\GatewayRouterService();
            return response()->json($router->getAvailableServices());
        });

        // Legacy direct auth routes (keeping for backwards compatibility)
        Route::post("login", [AuthController::class, "login"]);

        // Gateway routing - route all service requests
        Route::any('{service}/{path?}', [GatewayController::class, 'route'])
             ->where(['service' => '[a-zA-Z0-9\-_]+', 'path' => '.*']);
    });

    // Legacy API routes (when accessed via /api/ from Nginx)
    Route::get('services/status', function (GatewayController $gateway) {
        $router = new \App\Services\GatewayRouterService();
        return response()->json($router->getAvailableServices());
    });



    // Legacy direct auth routes (keeping for backwards compatibility)
    Route::post("login", [AuthController::class, "login"]);

    // Gateway routing - route all service requests
    Route::any('{service}/{path?}', [GatewayController::class, 'route'])
         ->where(['service' => '[a-zA-Z0-9\-_]+', 'path' => '.*']);
});
