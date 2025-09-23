<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use Exception;
use Illuminate\Support\Facades\Log;

class ConsumeMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rabbitmq:consume 
                            {queue? : Queue to consume (default: all configured queues)}
                            {--timeout=0 : Timeout in seconds (0 for no timeout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consume messages from RabbitMQ queues';

    /**
     * RabbitMQ service instance.
     */
    protected RabbitMQService $rabbitMQ;

    /**
     * Flag to determine if the consumer should continue running.
     */
    protected bool $shouldRun = true;

    /**
     * Create a new command instance.
     *
     * @param RabbitMQService $rabbitMQ
     */
    public function __construct(RabbitMQService $rabbitMQ)
    {
        parent::__construct();
        $this->rabbitMQ = $rabbitMQ;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $queueName = $this->argument('queue');
        $timeout = (int) $this->option('timeout');

        $this->info('Starting RabbitMQ consumer...');
        
        // Set up signal handling for graceful shutdown
        if (extension_loaded('pcntl')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }

        try {
            $this->rabbitMQ->connect();

            if ($queueName) {
                $this->consumeQueue($queueName);
            } else {
                $this->consumeAllQueues();
            }

            $this->info('Consumer stopped.');
            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to consume messages: ' . $e->getMessage());
            Log::error('Consumer error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Consume messages from a specific queue.
     *
     * @param string $queueName
     * @return void
     */
    protected function consumeQueue(string $queueName): void
    {
        $this->info("Consuming messages from queue: {$queueName}");
        
        $this->rabbitMQ->consume($queueName, function ($data, $message) use ($queueName) {
            $this->processMessage($data, $queueName);
            
            // Check for signals if pcntl is available
            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }
            
            return $this->shouldRun;
        });
    }

    /**
     * Consume messages from all configured queues.
     *
     * @return void
     */
    protected function consumeAllQueues(): void
    {
        $queues = [
            'auth.events',
            'addresses.events',
            'baskets.events',
            'products.events',
            'orders.events',
            'notifications.events',
        ];

        $this->info('Consuming messages from all queues: ' . implode(', ', $queues));
        
        // For simplicity, we'll just listen to the first queue
        // In a production environment, you would want to spawn multiple consumers or use a more sophisticated approach
        $this->consumeQueue($queues[0]);
    }

    /**
     * Process a received message.
     *
     * @param array $data
     * @param string $queueName
     * @return void
     */
    protected function processMessage(array $data, string $queueName): void
    {
        $this->info("Received message from {$queueName}:");
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
        
        // Process message based on queue name and message type
        switch ($queueName) {
            case 'auth.events':
                $this->processAuthEvent($data);
                break;
                
            case 'addresses.events':
                $this->processAddressEvent($data);
                break;
                
            // Add more cases for other queues
                
            default:
                $this->warn("No specific handler for queue: {$queueName}");
                break;
        }
    }

    /**
     * Process authentication events.
     *
     * @param array $data
     * @return void
     */
    protected function processAuthEvent(array $data): void
    {
        $eventType = $data['event'] ?? 'unknown';
        
        $this->info("Processing auth event: {$eventType}");
        
        // Handle different types of auth events
        switch ($eventType) {
            case 'user.created':
                // Handle user creation
                break;
                
            case 'user.updated':
                // Handle user update
                break;
                
            // Add more cases as needed
        }
    }

    /**
     * Process address events.
     *
     * @param array $data
     * @return void
     */
    protected function processAddressEvent(array $data): void
    {
        $eventType = $data['event'] ?? 'unknown';
        
        $this->info("Processing address event: {$eventType}");
        
        // Handle different types of address events
        switch ($eventType) {
            case 'address.created':
                // Handle address creation
                break;
                
            case 'address.updated':
                // Handle address update
                break;
                
            // Add more cases as needed
        }
    }

    /**
     * Handle shutdown signals.
     *
     * @param int $signal
     * @return void
     */
    public function shutdown(int $signal): void
    {
        $this->info("Received signal {$signal}, shutting down...");
        $this->shouldRun = false;
    }
}