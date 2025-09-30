<?php

namespace Shared\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMQRequestHandlerService
{
    protected ?AMQPStreamConnection $connection = null;
    protected $channel;
    protected array $config;
    protected string $serviceName;
    protected string $serviceUrl;
    protected bool $shouldContinueConsuming = true;

    public function __construct(string $serviceName, string $serviceUrl = null, array $config = null)
    {
        $this->serviceName = $serviceName;
        $this->serviceUrl = $serviceUrl ?: 'http://localhost:8000'; // Default to local service
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
            
            // Declare the request queue for this service with dead letter exchange
            $queueName = "{$this->serviceName}.requests";
            $arguments = [
                'x-dead-letter-exchange' => ['S', 'microservices.dlx'],
                'x-dead-letter-routing-key' => ['S', 'dead_letter']
            ];
            $this->channel->queue_declare(
                $queueName,
                false, // passive
                true,  // durable
                false, // exclusive
                false, // auto_delete
                false, // nowait
                $arguments
            );

            // Set QoS to handle one message at a time
            $this->channel->basic_qos(
                0,    // prefetch_size
                1,    // prefetch_count
                false // global
            );
            
            Log::info("RabbitMQ Request Handler connected for service: {$this->serviceName}");
        } catch (Exception $e) {
            Log::error("Failed to connect RabbitMQ Request Handler: " . $e->getMessage());
            throw $e;
        }
    }

    public function startListening(): void
    {
        if (!$this->connection || !$this->connection->isConnected()) {
            $this->connect();
        }

        $queueName = "{$this->serviceName}.requests";
        
        $this->channel->basic_consume(
            $queueName,
            '',     // consumer_tag
            false,  // no_local
            false,  // no_ack
            false,  // exclusive
            false,  // nowait
            [$this, 'handleRequest']
        );

        Log::info("Started listening for requests on queue: {$queueName}");

        while ($this->connection->isConnected() && $this->shouldContinueConsuming) {
            $this->channel->wait();
        }

        Log::info("Stopped listening for requests on queue: {$queueName}");
    }

    public function handleRequest(AMQPMessage $message): void
    {
        try {
            $requestData = json_decode($message->body, true);
            
            if (!$requestData) {
                throw new Exception('Invalid request data');
            }

            Log::info("Processing RabbitMQ request", [
                'correlation_id' => $message->get('correlation_id'),
                'service' => $this->serviceName,
                'method' => $requestData['method'] ?? 'unknown',
                'path' => $requestData['path'] ?? 'unknown'
            ]);

            // Extract request components
            $method = strtolower($requestData['method'] ?? 'get');
            $path = $requestData['path'] ?? '';
            $data = $requestData['data'] ?? [];
            $headers = $requestData['headers'] ?? [];

            // Build the full URL to the local service
            $fullUrl = rtrim($this->serviceUrl, '/') . '/api/' . ltrim($path, '/');

            // Prepare HTTP client with headers
            $httpClient = Http::withHeaders($headers);

            // Set timeout if needed
            $httpClient = $httpClient->timeout(25); // 25s to leave room for RabbitMQ timeout

            // Make the HTTP request to the local service
            $httpResponse = $httpClient->{$method}($fullUrl, $data);

            // Prepare response data
            $responseData = [
                'data' => $httpResponse->json(),
                'status' => $httpResponse->status(),
                'headers' => $httpResponse->headers()
            ];

            // Send response back via RabbitMQ
            $this->sendResponse($message, $responseData);

            // Acknowledge the message
            $this->channel->basic_ack($message->getDeliveryTag());

            Log::info("Request processed successfully", [
                'correlation_id' => $message->get('correlation_id'),
                'status' => $httpResponse->status()
            ]);

        } catch (Exception $e) {
            Log::error("Error processing RabbitMQ request", [
                'correlation_id' => $message->get('correlation_id'),
                'error' => $e->getMessage()
            ]);

            // Send error response
            $errorResponse = [
                'data' => ['error' => $e->getMessage()],
                'status' => 500,
                'headers' => []
            ];

            try {
                $this->sendResponse($message, $errorResponse);
            } catch (Exception $sendError) {
                Log::error("Failed to send error response: " . $sendError->getMessage());
            }

            // Acknowledge the message to prevent redelivery
            $this->channel->basic_ack($message->getDeliveryTag());
        }
    }

    protected function sendResponse(AMQPMessage $requestMessage, array $responseData): void
    {
        $correlationId = $requestMessage->get('correlation_id');
        $replyTo = $requestMessage->get('reply_to');

        if (!$correlationId || !$replyTo) {
            Log::warning("Cannot send response: missing correlation_id or reply_to");
            return;
        }

        $responseBody = json_encode($responseData);
        $responseMessage = new AMQPMessage(
            $responseBody,
            [
                'content_type' => 'application/json',
                'correlation_id' => $correlationId,
            ]
        );

        $this->channel->basic_publish($responseMessage, '', $replyTo);

        Log::debug("Response sent", [
            'correlation_id' => $correlationId,
            'reply_to' => $replyTo
        ]);
    }

    public function stopListening(): void
    {
        $this->shouldContinueConsuming = false;
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
        
        Log::info("RabbitMQ Request Handler disconnected for service: {$this->serviceName}");
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}