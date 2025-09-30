<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Shared\Exceptions\ServiceUnavailableException;
use Shared\Services\RabbitMQClientService;

class GatewayRouterService
{
    protected RabbitMQClientService $rabbitMQClient;

    public function __construct()
    {
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Service configuration mapping.
     *
     * @var array
     */
    protected $serviceMap = [
        'auth' => [
            'url' => 'AUTH_SERVICE_URL',
            'timeout' => 30
        ],
        'addresses' => [
            'url' => 'ADDRESSES_SERVICE_URL',
            'timeout' => 30
        ],
        'products' => [
            'url' => 'PRODUCTS_SERVICE_URL',
            'timeout' => 30
        ],
        'baskets' => [
            'url' => 'BASKETS_SERVICE_URL',
            'timeout' => 30
        ],
        'orders' => [
            'url' => 'ORDERS_SERVICE_URL',
            'timeout' => 30
        ],
        'messages-broker' => [
            'url' => 'MESSAGES_BROKER_URL',
            'timeout' => 30
        ],
        'newsletters' => [
            'url' => 'NEWSLETTERS_SERVICE_URL',
            'timeout' => 30
        ],
        'deliveries' => [
            'url' => 'DELIVERIES_SERVICE_URL',
            'timeout' => 30
        ],
        'sav' => [
            'url' => 'SAV_SERVICE_URL',
            'timeout' => 30
        ],
        'contacts' => [
            'url' => 'CONTACTS_SERVICE_URL',
            'timeout' => 30
        ],
        'websites' => [
            'url' => 'WEBSITES_SERVICE_URL',
            'timeout' => 30
        ],
        'questions' => [
            'url' => 'QUESTIONS_SERVICE_URL',
            'timeout' => 30
        ]
    ];

    /**
     * Route a request to the appropriate microservice.
     * All requests now go through the message broker for consistency.
     *
     * @param string $service
     * @param string $path
     * @param Request $request
     * @return array
     * @throws ServiceUnavailableException
     */
    public function routeRequest($service, $path, Request $request)
    {
        // Validate service exists
        if (!isset($this->serviceMap[$service])) {
            throw new ServiceUnavailableException("Unknown service: {$service}");
        }

        // For now, route all services via HTTP until RabbitMQ consumers are properly configured
        // TODO: Implement proper RabbitMQ consumers for each service
        return $this->routeViaHttp($service, $path, $request);
        
        /* Future implementation with RabbitMQ:
        // Auth service login/register endpoints use HTTP for immediate response
        if ($service === 'auth' && in_array($path, ['login', 'register', 'refresh', 'validate-token'])) {
            return $this->routeViaHttp($service, $path, $request);
        }

        // All other services use message broker for fully asynchronous architecture
        return $this->routeViaMessageBroker($service, $path, $request);
        */
    }

    /**
     * Route request via HTTP (for auth service only).
     */
    protected function routeViaHttp($service, $path, Request $request)
    {
        $config = $this->serviceMap[$service];
        $serviceUrl = env($config['url']);
        
        // Fallback URLs for services if env variables are not available
        if (!$serviceUrl) {
            $fallbackUrls = [
                'auth' => 'http://auth-service:8001',
                'addresses' => 'http://addresses-service:8009',
                'products' => 'http://products-service:8003',
                'baskets' => 'http://baskets-service:8005',
                'orders' => 'http://orders-service:8004',
                'deliveries' => 'http://deliveries-service:8006',
                'newsletters' => 'http://newsletters-service:8007',
                'sav' => 'http://sav-service:8008',
                'contacts' => 'http://contacts-service:8010',
                'messages-broker' => 'http://messages-broker:8002',
            ];
            
            if (isset($fallbackUrls[$service])) {
                $serviceUrl = $fallbackUrls[$service];
                \Log::warning("Using fallback URL for service: {$service} -> {$serviceUrl}");
            } else {
                throw new ServiceUnavailableException("Service URL not configured for: {$service}");
            }
        }

        // Build the full URL
        $fullUrl = rtrim($serviceUrl, '/') . '/api/' . ltrim($path, '/');
        
        // Debug logging
        \Log::info("Gateway routing via HTTP", [
            'service' => $service,
            'path' => $path,
            'serviceUrl' => $serviceUrl,
            'fullUrl' => $fullUrl,
            'method' => $request->method()
        ]);
        
        // Prepare headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Gateway-Service' => 'api-gateway',
            'X-Request-ID' => $request->header('X-Request-ID', uniqid()),
        ];

        // Forward authorization header if present
        if ($request->bearerToken()) {
            $headers['Authorization'] = 'Bearer ' . $request->bearerToken();
        }

        // Add any auth user context
        if ($request->has('auth_user')) {
            $headers['X-Auth-User'] = base64_encode(json_encode($request->get('auth_user')));
        }

        try {
            // Make the request to the microservice
            $response = Http::withHeaders($headers)
                ->timeout($config['timeout'])
                ->{strtolower($request->method())}($fullUrl, $request->all());

            return [
                'data' => $response->json(),
                'status' => $response->status(),
                'headers' => $response->headers()
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new ServiceUnavailableException("Cannot connect to {$service} service");
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Forward the error response from the service
            return [
                'data' => $e->response ? $e->response->json() : ['error' => 'Service error'],
                'status' => $e->response ? $e->response->status() : 500
            ];
        } catch (\Exception $e) {
            throw new ServiceUnavailableException("Error routing to {$service}: " . $e->getMessage());
        }
    }

    /**
     * Route request via message broker (for all services except auth).
     */
    protected function routeViaMessageBroker($service, $path, Request $request)
    {
        $config = $this->serviceMap[$service];
        
        // Debug logging
        \Log::info("Gateway routing via Message Broker", [
            'service' => $service,
            'path' => $path,
            'method' => $request->method()
        ]);
        
        // Prepare headers
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Gateway-Service' => 'api-gateway',
            'X-Request-ID' => $request->header('X-Request-ID', uniqid()),
        ];

        // Forward authorization header if present
        if ($request->bearerToken()) {
            $headers['Authorization'] = 'Bearer ' . $request->bearerToken();
        }

        // Add any auth user context
        if ($request->has('auth_user')) {
            $headers['X-Auth-User'] = base64_encode(json_encode($request->get('auth_user')));
        }

        try {
            // Send request via message broker using RPC pattern
            $response = $this->rabbitMQClient->sendRequest(
                $service,
                $request->method(),
                $path,
                $request->all(),
                $headers,
                $config['timeout']
            );

            return [
                'data' => $response['data'] ?? $response,
                'status' => $response['status'] ?? 200,
                'headers' => $response['headers'] ?? []
            ];

        } catch (\Exception $e) {
            throw new ServiceUnavailableException("Error routing to {$service} via message broker: " . $e->getMessage());
        }
    }

    /**
     * Check if a service is available by verifying RabbitMQ consumers.
     * In the fully asynchronous architecture, a service is available if it has active consumers
     * listening on its request queue.
     *
     * @param string $service
     * @return bool
     */
    public function isServiceAvailable($service)
    {
        if (!isset($this->serviceMap[$service])) {
            return false;
        }

        try {
            // Check if the service has active consumers on its request queue
            $queueName = $service . '.requests';
            
            // Use RabbitMQ Management API to check for active consumers
            $managementUrl = 'http://' . env('RABBITMQ_HOST', 'rabbitmq') . ':15672';
            $username = env('RABBITMQ_USER', 'guest');
            $password = env('RABBITMQ_PASSWORD', 'guest');
            $vhost = env('RABBITMQ_VHOST', '/');
            
            // URL encode the vhost (default "/" becomes "%2F")
            $encodedVhost = urlencode($vhost);
            
            // Get queue information from RabbitMQ Management API
            $queueInfoUrl = $managementUrl . '/api/queues/' . $encodedVhost . '/' . $queueName;
            
            $response = Http::withBasicAuth($username, $password)
                ->timeout(5)
                ->get($queueInfoUrl);
            
            // Debug logging
            \Log::info("Service availability check for {$service}: HTTP {$response->status()}", [
                'queue' => $queueName,
                'url' => $queueInfoUrl,
                'successful' => $response->successful()
            ]);
            
            if ($response->successful()) {
                $queueData = $response->json();
                $consumers = $queueData['consumers'] ?? 0;
                
                \Log::info("Queue {$queueName} found with {$consumers} consumers");
                
                // Service is available if it has consumers listening on the queue
                return $consumers > 0;
            }
            
            // If queue doesn't exist (404) or any other error, service is not available
            \Log::info("Queue {$queueName} not available (HTTP {$response->status()})");
            return false;
        } catch (\Exception $e) {
            // If we can't connect to RabbitMQ Management API, assume service is unavailable
            \Log::warning("Could not check service availability for {$service}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the list of available services.
     *
     * @return array
     */
    public function getAvailableServices()
    {
        $services = [];
        
        foreach (array_keys($this->serviceMap) as $service) {
            // Get URL from environment or fallback to internal URL
            $envUrl = env($this->serviceMap[$service]['url']);
            $internalUrl = $this->getInternalServiceUrl($service);
            
            // Use RabbitMQ-based service availability check
            $available = $this->isServiceAvailable($service);
            
            $services[$service] = [
                'available' => $available,
                'url' => $envUrl ?: $internalUrl
            ];
        }

        return $services;
    }

    /**
     * Get the internal Docker network URL for a service.
     *
     * @param string $service
     * @return string|null
     */
    private function getInternalServiceUrl($service)
    {
        // Map service names to internal Docker container names with correct ports
        $internalServiceMap = [
            'auth' => 'http://auth-service:8001',
            'addresses' => 'http://addresses-service:8009',
            'products' => 'http://products-service:8003',
            'baskets' => 'http://baskets-service:8005',
            'orders' => 'http://orders-service:8004',
            'deliveries' => 'http://deliveries-service:8006',
            'newsletters' => 'http://newsletters-service:8007',
            'sav' => 'http://sav-service:8008',
            'contacts' => 'http://contacts-service:8010',
            'messages-broker' => 'http://messages-broker:8002',
        ];

        return $internalServiceMap[$service] ?? null;
    }
}