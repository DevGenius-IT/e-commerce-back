<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Shared\Services\RabbitMQRequestHandlerService;
use Exception;

class ListenRabbitMQRequestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:listen-requests {--timeout=0 : Maximum execution time in seconds (0 = no timeout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen for incoming RabbitMQ requests and process them';

    protected RabbitMQRequestHandlerService $requestHandler;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting RabbitMQ Request Listener for orders-service...');

        try {
            // Initialize the request handler for this service
            $serviceUrl = env('APP_URL', 'http://localhost:8004');
            $this->requestHandler = new RabbitMQRequestHandlerService('orders', $serviceUrl);

            // Set up graceful shutdown
            $this->setupSignalHandlers();

            // Connect to RabbitMQ
            $this->requestHandler->connect();
            $this->info('Connected to RabbitMQ successfully');

            // Set timeout if specified
            $timeout = (int) $this->option('timeout');
            if ($timeout > 0) {
                $this->info("Setting timeout to {$timeout} seconds");
                $this->setupTimeout($timeout);
            }

            $this->info('Listening for requests on queue: orders.requests');
            $this->info('Press Ctrl+C to stop...');

            // Start listening for requests
            $this->requestHandler->startListening();

        } catch (Exception $e) {
            $this->error('Failed to start RabbitMQ listener: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info('RabbitMQ Request Listener stopped');
        return Command::SUCCESS;
    }

    /**
     * Set up signal handlers for graceful shutdown
     */
    protected function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGQUIT, [$this, 'handleShutdown']);
        }
    }

    /**
     * Handle shutdown signals
     */
    public function handleShutdown(): void
    {
        $this->info('Received shutdown signal, stopping gracefully...');
        if ($this->requestHandler) {
            $this->requestHandler->stopListening();
            $this->requestHandler->disconnect();
        }
    }

    /**
     * Set up timeout for the command
     */
    protected function setupTimeout(int $timeout): void
    {
        if (function_exists('pcntl_alarm')) {
            pcntl_signal(SIGALRM, function () {
                $this->info('Timeout reached, stopping...');
                $this->handleShutdown();
            });
            pcntl_alarm($timeout);
        }
    }
}