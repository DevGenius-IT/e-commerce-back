<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RabbitMQService;
use PhpAmqpLib\Exchange\AMQPExchangeType;

class SetupRabbitMQCommand extends Command
{
    protected $signature = 'rabbitmq:setup {--force : Force recreation of exchanges and queues}';
    protected $description = 'Setup RabbitMQ exchanges and queues for all microservices';

    private array $exchanges = [
        'microservices' => [
            'type' => AMQPExchangeType::TOPIC,
            'durable' => true,
            'auto_delete' => false
        ],
        'microservices.dlx' => [
            'type' => AMQPExchangeType::TOPIC,
            'durable' => true,
            'auto_delete' => false
        ]
    ];

    private array $queues = [
        // Request queues for synchronous communication via RabbitMQ
        'auth.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['auth.request']
        ],
        'addresses.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['addresses.request']
        ],
        'baskets.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['baskets.request']
        ],
        'products.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['products.request']
        ],
        'orders.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['orders.request']
        ],
        'newsletters.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['newsletters.request']
        ],
        'deliveries.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['deliveries.request']
        ],
        'sav.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['sav.request']
        ],
        'contacts.requests' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['contacts.request']
        ],
        // Event queues for asynchronous communication
        'auth.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['auth.*', 'user.*']
        ],
        'addresses.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['address.*']
        ],
        'baskets.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['basket.*', 'cart.*']
        ],
        'products.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['product.*', 'inventory.*']
        ],
        'orders.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['order.*', 'payment.*']
        ],
        'notifications.events' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['notification.*', 'email.*']
        ],
        'dead_letter_queue' => [
            'durable' => true,
            'auto_delete' => false,
            'routing_keys' => ['#'],
            'exchange' => 'microservices.dlx'
        ]
    ];

    public function __construct(
        private RabbitMQService $rabbitMQ
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $force = $this->option('force');

        try {
            $this->info('Setting up RabbitMQ infrastructure...');
            
            $this->rabbitMQ->connect();
            $channel = $this->rabbitMQ->getChannel();

            // Setup exchanges
            $this->setupExchanges($channel, $force);

            // Setup queues
            $this->setupQueues($channel, $force);

            $this->rabbitMQ->disconnect();

            $this->info('✅ RabbitMQ setup completed successfully!');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to setup RabbitMQ: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function setupExchanges($channel, bool $force): void
    {
        foreach ($this->exchanges as $name => $config) {
            if ($force) {
                try {
                    $channel->exchange_delete($name);
                    $this->line("Deleted existing exchange: {$name}");
                } catch (\Exception $e) {
                    // Exchange might not exist
                }
            }

            $channel->exchange_declare(
                $name,
                $config['type'],
                false, // passive
                $config['durable'],
                $config['auto_delete']
            );

            $this->line("✓ Created exchange: {$name}");
        }
    }

    private function setupQueues($channel, bool $force): void
    {
        foreach ($this->queues as $name => $config) {
            if ($force) {
                try {
                    $channel->queue_delete($name);
                    $this->line("Deleted existing queue: {$name}");
                } catch (\Exception $e) {
                    // Queue might not exist
                }
            }

            // Declare queue with dead letter exchange only for event queues
            $arguments = [];
            if ($name !== 'dead_letter_queue' && strpos($name, '.events') !== false) {
                $arguments = [
                    'x-dead-letter-exchange' => ['S', 'microservices.dlx'],
                    'x-dead-letter-routing-key' => ['S', 'dead_letter']
                ];
            }

            $channel->queue_declare(
                $name,
                false, // passive
                $config['durable'],
                false, // exclusive
                $config['auto_delete'],
                false, // nowait
                $arguments
            );

            // Bind queue to exchange with routing keys
            $exchange = $config['exchange'] ?? 'microservices';
            foreach ($config['routing_keys'] as $routingKey) {
                $channel->queue_bind($name, $exchange, $routingKey);
                $this->line("  → Bound '{$name}' to '{$exchange}' with key '{$routingKey}'");
            }

            $this->line("✓ Created queue: {$name}");
        }
    }
}