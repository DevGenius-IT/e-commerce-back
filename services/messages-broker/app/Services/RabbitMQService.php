<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use Exception;
use Illuminate\Support\Facades\Log;

class RabbitMQService
{
    protected ?AMQPStreamConnection $connection = null;
    protected $channel;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connect(): void
    {
        try {
            $this->connection = new AMQPStreamConnection(
                $this->config['host'],
                $this->config['port'],
                $this->config['user'],
                $this->config['password'],
                $this->config['vhost']
            );
            
            $this->channel = $this->connection->channel();
            
            // Declare exchanges
            foreach ($this->config['exchanges'] as $exchange) {
                $this->channel->exchange_declare(
                    $exchange['name'],
                    $exchange['type'],
                    false,
                    $exchange['durable'],
                    $exchange['auto_delete']
                );
            }
            
            // Declare queues and bind them
            foreach ($this->config['queues'] as $queue) {
                $this->channel->queue_declare(
                    $queue['name'],
                    false,
                    $queue['durable'],
                    $queue['exclusive'],
                    $queue['auto_delete']
                );
                
                // Bind queue to exchange with routing keys
                if (isset($queue['routing_keys'])) {
                    foreach ($queue['routing_keys'] as $routingKey) {
                        $this->channel->queue_bind(
                            $queue['name'],
                            $this->config['exchanges']['events']['name'],
                            $routingKey
                        );
                    }
                }
            }
            
            Log::info('RabbitMQ connection established successfully');
        } catch (Exception $e) {
            Log::error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    public function publish(string $exchange, string $routingKey, array $data): void
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connect();
        }

        $messageBody = json_encode($data);
        $message = new AMQPMessage(
            $messageBody,
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT
            ]
        );

        $this->channel->basic_publish($message, $exchange, $routingKey);
        
        Log::info("Message published to {$exchange} with routing key {$routingKey}");
    }

    public function consume(string $queueName, callable $callback): void
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connect();
        }

        // Set QoS
        $this->channel->basic_qos(
            $this->config['qos']['prefetch_size'],
            $this->config['qos']['prefetch_count'],
            $this->config['qos']['global']
        );

        $this->channel->basic_consume(
            $queueName,
            $this->config['consumer']['tag'],
            $this->config['consumer']['no_local'],
            $this->config['consumer']['no_ack'],
            $this->config['consumer']['exclusive'],
            $this->config['consumer']['nowait'],
            function ($message) use ($callback) {
                try {
                    $data = json_decode($message->body, true);
                    $callback($data, $message);
                    $message->ack();
                } catch (Exception $e) {
                    Log::error('Error processing message: ' . $e->getMessage());
                    $message->nack(true);
                }
            }
        );

        Log::info("Starting to consume messages from queue: {$queueName}");

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function getQueueStats(string $queueName): array
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connect();
        }

        list($queue, $messageCount, $consumerCount) = $this->channel->queue_declare(
            $queueName,
            true
        );

        return [
            'queue' => $queue,
            'message_count' => $messageCount,
            'consumer_count' => $consumerCount
        ];
    }

    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    public function disconnect(): void
    {
        if ($this->channel) {
            $this->channel->close();
        }
        
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
        
        Log::info('RabbitMQ connection closed');
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}