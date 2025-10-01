<?php

namespace Shared\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;
use Illuminate\Support\Facades\Log;

class RabbitMQClientService
{
    protected ?AMQPStreamConnection $connection = null;
    protected $channel;
    protected array $config;
    protected array $responses = [];
    protected string $callbackQueue;

    public function __construct(array $config = null)
    {
        $this->config = $config ?: [
            'host' => env('RABBITMQ_HOST', 'rabbitmq'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ];
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
            
            // Create a temporary callback queue for replies
            list($this->callbackQueue, ,) = $this->channel->queue_declare(
                '', // let RabbitMQ generate queue name
                false, // passive
                false, // durable
                true, // exclusive
                true // auto_delete
            );

            // Set up consumer for replies
            $this->channel->basic_consume(
                $this->callbackQueue,
                '',
                false,
                true,
                false,
                false,
                [$this, 'onResponse']
            );
            
            Log::info('RabbitMQ client connection established successfully');
        } catch (Exception $e) {
            Log::error('Failed to connect to RabbitMQ: ' . $e->getMessage());
            throw $e;
        }
    }

    public function onResponse(AMQPMessage $message): void
    {
        $correlationId = $message->get('correlation_id');
        if (isset($this->responses[$correlationId])) {
            $this->responses[$correlationId] = $message->body;
        }
    }

    /**
     * Send a request via RabbitMQ and wait for response (RPC pattern)
     *
     * @param string $service Target service name
     * @param string $method HTTP method
     * @param string $path Request path
     * @param array $data Request data
     * @param array $headers Request headers
     * @param int $timeout Timeout in seconds
     * @return array
     * @throws Exception
     */
    public function sendRequest(
        string $service,
        string $method,
        string $path,
        array $data = [],
        array $headers = [],
        int $timeout = 30
    ): array {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connect();
        }

        $correlationId = uniqid();
        $requestData = [
            'method' => $method,
            'path' => $path,
            'data' => $data,
            'headers' => $headers,
            'timestamp' => time(),
        ];

        // Prepare the message
        $messageBody = json_encode($requestData);
        $message = new AMQPMessage(
            $messageBody,
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                'correlation_id' => $correlationId,
                'reply_to' => $this->callbackQueue,
            ]
        );

        // Initialize response placeholder
        $this->responses[$correlationId] = null;

        // Publish message to service queue
        $queueName = "{$service}.requests";
        $this->channel->basic_publish($message, '', $queueName);

        Log::info("Request sent to {$service} via RabbitMQ", [
            'correlation_id' => $correlationId,
            'method' => $method,
            'path' => $path,
        ]);

        // Wait for response
        $startTime = time();
        while ($this->responses[$correlationId] === null) {
            if (time() - $startTime > $timeout) {
                unset($this->responses[$correlationId]);
                throw new Exception("Request timeout for service {$service}");
            }

            $this->channel->wait(null, false, 5); // Wait 5s
        }

        $responseData = json_decode($this->responses[$correlationId], true);
        unset($this->responses[$correlationId]);

        if (!$responseData) {
            throw new Exception("Invalid response from service {$service}");
        }

        return $responseData;
    }

    /**
     * Publish a message without waiting for response
     *
     * @param string $exchange Exchange name
     * @param string $routingKey Routing key
     * @param array $data Message data
     * @return void
     * @throws Exception
     */
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

    /**
     * Check if RabbitMQ connection is healthy
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->connection && $this->connection->isConnected();
    }

    /**
     * Close the connection
     *
     * @return void
     */
    public function disconnect(): void
    {
        if ($this->channel) {
            $this->channel->close();
        }
        
        if ($this->connection && $this->connection->isConnected()) {
            $this->connection->close();
        }
        
        Log::info('RabbitMQ client connection closed');
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}