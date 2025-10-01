<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Shared\Services\RabbitMQRequestHandlerService;

echo "Starting RabbitMQ Request Listener for orders-service...\n";

try {
    // Initialize the request handler for this service
    $serviceUrl = env('APP_URL', 'http://localhost:8004');
    $requestHandler = new RabbitMQRequestHandlerService('orders', $serviceUrl);

    // Connect to RabbitMQ
    $requestHandler->connect();
    echo "Connected to RabbitMQ successfully\n";
    echo "Listening for requests on queue: orders.requests\n";
    echo "Press Ctrl+C to stop...\n";

    // Set up signal handlers for graceful shutdown
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, function() use ($requestHandler) {
            echo "Received shutdown signal, stopping gracefully...\n";
            $requestHandler->stopListening();
            $requestHandler->disconnect();
            exit(0);
        });
        pcntl_signal(SIGINT, function() use ($requestHandler) {
            echo "Received shutdown signal, stopping gracefully...\n";
            $requestHandler->stopListening();
            $requestHandler->disconnect();
            exit(0);
        });
    }

    // Start listening for requests
    $requestHandler->startListening();

} catch (Exception $e) {
    echo "Failed to start RabbitMQ listener: " . $e->getMessage() . "\n";
    exit(1);
}

echo "RabbitMQ Request Listener stopped\n";