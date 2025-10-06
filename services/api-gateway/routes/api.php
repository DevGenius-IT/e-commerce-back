<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GatewayController;
use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('health', function () {
    return response()->json(['status' => 'healthy', 'service' => 'api-gateway']);
});

// Simple health check for probes (text response)
Route::get('simple-health', function () {
    return response('healthy', 200)->header('Content-Type', 'text/plain');
});

// Test RabbitMQ connection
Route::get('test-rabbitmq', function () {
    try {
        $config = [
            'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq',
            'port' => getenv('RABBITMQ_PORT') ?: 5672,
            'user' => getenv('RABBITMQ_USER') ?: 'guest',
            'password' => getenv('RABBITMQ_PASSWORD') ?: 'guest',
            'vhost' => getenv('RABBITMQ_VHOST') ?: '/',
        ];

        // Test connection using socket instead of RabbitMQClientService to avoid logging issues
        $connection = @fsockopen($config['host'], $config['port'], $errno, $errstr, 5);
        $isConnected = $connection !== false;

        if ($connection) {
            fclose($connection);
        }

        return response()->json([
            'status' => 'success',
            'rabbitmq_connected' => $isConnected,
            'config' => [
                'host' => $config['host'],
                'port' => $config['port'],
            ],
            'message' => $isConnected ? 'RabbitMQ connection successful' : 'RabbitMQ not connected',
            'error' => $isConnected ? null : "$errstr ($errno)"
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'RabbitMQ connection test failed: ' . $e->getMessage(),
            'config_attempted' => [
                'host' => getenv('RABBITMQ_HOST') ?: 'rabbitmq',
                'port' => getenv('RABBITMQ_PORT') ?: 5672,
            ]
        ], 500);
    }
});

// Service status endpoint
Route::get('services/status', function (GatewayController $gateway) {
    $router = new \App\Services\GatewayRouterService();
    return response()->json($router->getAvailableServices());
});

// V1 routes prefix (keeping for backwards compatibility)
Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json(['status' => 'healthy', 'service' => 'api-gateway', 'version' => 'v1']);
    });

    Route::get('services/status', function (GatewayController $gateway) {
        $router = new \App\Services\GatewayRouterService();
        return response()->json($router->getAvailableServices());
    });

    Route::post("login", [AuthController::class, "login"]);

    Route::any('{service}/{path?}', [GatewayController::class, 'route'])
         ->where(['service' => '[a-zA-Z0-9\-_]+', 'path' => '.*']);
});

// Direct auth routes
Route::post("login", [AuthController::class, "login"]);

// Gateway routing - route all service requests
Route::any('{service}/{path?}', [GatewayController::class, 'route'])
     ->where(['service' => '[a-zA-Z0-9\-_]+', 'path' => '.*']);
