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
        ]
    ];

    /**
     * Route a request to the appropriate microservice.
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

        // Use HTTP for auth service, addresses service, products service, baskets service and orders service, message broker for others
        if ($service === 'auth' || $service === 'addresses' || $service === 'products' || $service === 'baskets' || $service === 'orders') {
            return $this->routeViaHttp($service, $path, $request);
        } else {
            return $this->routeViaMessageBroker($service, $path, $request);
        }
    }

    /**
     * Route request via HTTP (for auth service only).
     */
    protected function routeViaHttp($service, $path, Request $request)
    {
        $config = $this->serviceMap[$service];
        $serviceUrl = env($config['url']);
        
        if (!$serviceUrl) {
            throw new ServiceUnavailableException("Service URL not configured for: {$service}");
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
     * Check if a service is available.
     *
     * @param string $service
     * @return bool
     */
    public function isServiceAvailable($service)
    {
        if (!isset($this->serviceMap[$service])) {
            return false;
        }

        $config = $this->serviceMap[$service];
        $serviceUrl = env($config['url']);
        
        if (!$serviceUrl) {
            return false;
        }

        try {
            // Use a simple HEAD request to the base API endpoint
            // Different services may have different health check endpoints
            if ($service === 'auth') {
                // For auth service, check if we can reach the base API
                $response = Http::timeout(5)->get(rtrim($serviceUrl, '/') . '/api/login');
                // A 405 Method Not Allowed is actually good - it means the service is responding
                return $response->successful() || $response->status() === 405;
            } else {
                // For other services, try the health endpoint first, fallback to any endpoint
                $response = Http::timeout(5)->get(rtrim($serviceUrl, '/') . '/api/health');
                if ($response->successful()) {
                    return true;
                }
                // Fallback: any response (even 404) means the service is running
                $response = Http::timeout(5)->get(rtrim($serviceUrl, '/') . '/api/');
                return $response->status() !== 0; // Any HTTP response means service is up
            }
        } catch (\Exception $e) {
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
            $services[$service] = [
                'available' => $this->isServiceAvailable($service),
                'url' => env($this->serviceMap[$service]['url'])
            ];
        }

        return $services;
    }
}