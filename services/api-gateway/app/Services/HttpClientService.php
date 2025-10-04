<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shared\Exceptions\ServiceUnavailableException;

class HttpClientService
{
  /**
   * The base URI for the service.
   *
   * @var string
   */
  protected $baseUri;

  /**
   * The secret key for the service.
   *
   * @var string
   */
  protected $secret;

  /**
   * The service name.
   *
   * @var string
   */
  protected $serviceName;

  /**
   * The message broker service.
   *
   * @var mixed
   */
  protected $messageBroker;

  public function __construct(string $service)
  {
    $this->serviceName = $service;
    $this->baseUri = config("services.{$service}.base_uri");
    $this->secret = config("services.{$service}.secret");
    
    // Initialize message broker if available
    $this->initializeMessageBroker();
  }

  /**
   * Initialize message broker connection.
   */
  protected function initializeMessageBroker()
  {
    try {
      // Create RabbitMQ service instance with configuration
      $rabbitConfig = [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchanges' => [
          'events' => [
            'name' => 'events',
            'type' => 'topic',
            'durable' => true,
            'auto_delete' => false
          ]
        ],
        'queues' => [],
        'qos' => [
          'prefetch_size' => 0,
          'prefetch_count' => 1,
          'global' => false
        ],
        'consumer' => [
          'tag' => 'http_client_consumer',
          'no_local' => false,
          'no_ack' => false,
          'exclusive' => false,
          'nowait' => false
        ]
      ];

      // Use dependency injection to get RabbitMQService if available
      if (class_exists('\App\Services\RabbitMQService')) {
        $this->messageBroker = app()->make('\App\Services\RabbitMQService', ['config' => $rabbitConfig]);
      }
    } catch (\Exception $e) {
      // Log::warning("Message broker initialization failed: " . $e->getMessage());
      $this->messageBroker = null;
    }
  }

  /**
   * Make a request to the service.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $data
   * @return array
   * @throws ServiceUnavailableException
   */
  public function request($method, $endpoint, $data = [])
  {
    try {
      // Determine the URL based on service and endpoint
      $url = $this->buildUrl($endpoint);

      // \Log::info("HttpClientService making request", [
      //   'service' => $this->serviceName,
      //   'method' => $method,
      //   'endpoint' => $endpoint,
      //   'url' => $url,
      //   'base_uri' => $this->baseUri
      // ]);

      $response = Http::withHeaders([
        "X-Service-Key" => $this->secret,
        "Accept" => "application/json",
      ])->$method($url, $data);

      // Publish event if message broker is available and this is a state-changing operation
      if ($this->messageBroker && $this->shouldPublishEvent($method, $endpoint, $response)) {
        $this->publishEvent($method, $endpoint, $data, $response->json());
      }

      return $response->json();
    } catch (\Exception $e) {
      throw new ServiceUnavailableException("Service unavailable");
    }
  }

  /**
   * Build the URL for the request based on service type and endpoint.
   *
   * @param string $endpoint
   * @return string
   */
  protected function buildUrl($endpoint)
  {
    // Remove trailing slash from base URI and leading slash from endpoint
    $baseUri = rtrim($this->baseUri, '/');
    $endpoint = ltrim($endpoint, '/');
    
    // All services use /api/ prefix
    return "{$baseUri}/api/{$endpoint}";
  }

  /**
   * Determine if an event should be published for this request.
   *
   * @param string $method
   * @param string $endpoint
   * @param \Illuminate\Http\Client\Response $response
   * @return bool
   */
  protected function shouldPublishEvent($method, $endpoint, $response)
  {
    // Only publish events for successful state-changing operations
    if (!$response->successful()) {
      return false;
    }

    // Define which endpoints should trigger events
    $eventTriggers = [
      'auth' => ['login', 'logout', 'register', 'refresh'],
      'addresses' => ['addresses', 'countries'],
      'products' => ['products', 'categories'],
      'orders' => ['orders', 'payments'],
      'baskets' => ['baskets', 'items']
    ];

    if (!isset($eventTriggers[$this->serviceName])) {
      return false;
    }

    $triggeredEndpoints = $eventTriggers[$this->serviceName];
    $endpointParts = explode('/', trim($endpoint, '/'));
    $mainEndpoint = $endpointParts[0] ?? '';

    return in_array($mainEndpoint, $triggeredEndpoints) && in_array(strtolower($method), ['post', 'put', 'delete']);
  }

  /**
   * Publish an event to the message broker.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $requestData
   * @param array $responseData
   */
  protected function publishEvent($method, $endpoint, $requestData, $responseData)
  {
    try {
      $routingKey = $this->generateRoutingKey($method, $endpoint);
      $eventData = [
        'service' => $this->serviceName,
        'method' => strtoupper($method),
        'endpoint' => $endpoint,
        'timestamp' => now()->toISOString(),
        'request_data' => $requestData,
        'response_data' => $responseData,
        'user_id' => $requestData['auth_user']['id'] ?? null
      ];

      $this->messageBroker->publish('events', $routingKey, $eventData);
      // Log::info("Event published: {$routingKey}");
    } catch (\Exception $e) {
      // Log::warning("Failed to publish event: " . $e->getMessage());
    }
  }

  /**
   * Generate routing key for the event.
   *
   * @param string $method
   * @param string $endpoint
   * @return string
   */
  protected function generateRoutingKey($method, $endpoint)
  {
    $endpointParts = explode('/', trim($endpoint, '/'));
    $action = $endpointParts[0] ?? 'unknown';
    
    return "{$this->serviceName}.{$action}." . strtolower($method);
  }

  /**
   * Make a GET request to the service.
   *
   * @param string $endpoint
   * @return array
   */
  public function get($endpoint)
  {
    return $this->request("get", $endpoint);
  }

  /**
   * Make a POST request to the service.
   *
   * @param string $endpoint
   * @param array $data
   * @return array
   */
  public function post($endpoint, $data)
  {
    return $this->request("post", $endpoint, $data);
  }

  /**
   * Make a PUT request to the service.
   *
   * @param string $endpoint
   * @param array $data
   * @return array
   */
  public function put($endpoint, $data)
  {
    return $this->request("put", $endpoint, $data);
  }

  /**
   * Make a DELETE request to the service.
   *
   * @param string $endpoint
   * @return array
   */
  public function delete($endpoint)
  {
    return $this->request("delete", $endpoint);
  }

  /**
   * Publish an event asynchronously without waiting for HTTP response.
   *
   * @param string $eventType
   * @param array $data
   * @return bool
   */
  public function publishEventAsync($eventType, $data = [])
  {
    if (!$this->messageBroker) {
      // Log::warning("Cannot publish async event: Message broker not available");
      return false;
    }

    try {
      $routingKey = "{$this->serviceName}.{$eventType}";
      $eventData = [
        'service' => $this->serviceName,
        'event_type' => $eventType,
        'timestamp' => now()->toISOString(),
        'data' => $data,
        'user_id' => $data['user_id'] ?? null
      ];

      $this->messageBroker->publish('events', $routingKey, $eventData);
      // Log::info("Async event published: {$routingKey}");
      return true;
    } catch (\Exception $e) {
      // Log::error("Failed to publish async event: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Publish authentication events specifically.
   *
   * @param string $action login|logout|register|refresh
   * @param array $data
   * @return bool
   */
  public function publishAuthEvent($action, $data = [])
  {
    return $this->publishEventAsync("auth.{$action}", $data);
  }

  /**
   * Check if message broker is available.
   *
   * @return bool
   */
  public function isMessageBrokerAvailable()
  {
    try {
      return $this->messageBroker && $this->messageBroker->isConnected();
    } catch (\Exception $e) {
      return false;
    }
  }

  /**
   * Fallback to HTTP request if message broker fails.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $data
   * @return array
   */
  public function requestWithFallback($method, $endpoint, $data = [])
  {
    try {
      // Try message broker first for async operations
      if ($this->isMessageBrokerAvailable() && $this->isAsyncOperation($method, $endpoint)) {
        $this->publishEventAsync($endpoint, $data);
        return ['status' => 'queued', 'message' => 'Request queued for processing'];
      }

      // Fallback to HTTP
      return $this->request($method, $endpoint, $data);
    } catch (\Exception $e) {
      // Last resort: direct HTTP request
      return $this->request($method, $endpoint, $data);
    }
  }

  /**
   * Determine if operation should be handled asynchronously.
   *
   * @param string $method
   * @param string $endpoint
   * @return bool
   */
  protected function isAsyncOperation($method, $endpoint)
  {
    $asyncOperations = [
      'notifications' => true,
      'emails' => true,
      'analytics' => true,
      'logs' => true
    ];

    $endpointParts = explode('/', trim($endpoint, '/'));
    $mainEndpoint = $endpointParts[0] ?? '';

    return isset($asyncOperations[$mainEndpoint]) && $asyncOperations[$mainEndpoint];
  }
}
