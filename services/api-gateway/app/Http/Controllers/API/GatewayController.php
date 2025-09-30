<?php

namespace App\Http\Controllers\API;

use Shared\Components\Controller;
use App\Services\GatewayRouterService;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GatewayController extends Controller
{
    /**
     * The gateway router service.
     *
     * @var GatewayRouterService
     */
    protected $gatewayRouter;

    /**
     * The authentication service.
     *
     * @var AuthService
     */
    protected $authService;

    public function __construct(GatewayRouterService $gatewayRouter, AuthService $authService)
    {
        $this->gatewayRouter = $gatewayRouter;
        $this->authService = $authService;
    }

    /**
     * Route any request to the appropriate microservice.
     *
     * @param Request $request
     * @param string $service
     * @param string $path
     * @return JsonResponse
     */
    public function route(Request $request, $service, $path = '')
    {
        // Debug log to ensure we reach this method
        \Log::info("GatewayController::route called", [
            'service' => $service,
            'path' => $path,
            'method' => $request->method(),
            'headers' => $request->headers->all()
        ]);
        
        // Extract and validate JWT token for protected routes
        $token = $request->bearerToken();
        
        // Check if this is a protected route
        if ($this->requiresAuthentication($service, $path)) {
            if (!$token) {
                return response()->json(['error' => 'Authentication required'], 401);
            }

            try {
                \Log::info("About to validate token", ['token_length' => strlen($token)]);
                
                $validation = $this->authService->validateToken($token);
                
                \Log::info("Token validation result", ['validation' => $validation]);
                
                // Check if validation response has the expected structure
                if (!$validation || !isset($validation['valid']) || !$validation['valid']) {
                    return response()->json(['error' => 'Invalid token'], 401);
                }
                
                // Add user context to request
                $request->merge(['auth_user' => $validation['user']]);
            } catch (\Exception $e) {
                \Log::error("Authentication service exception", [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['error' => 'Authentication service unavailable'], 503);
            }
        }

        // Route the request to the appropriate microservice
        try {
            \Log::info("About to call gatewayRouter->routeRequest", [
                'service' => $service,
                'path' => $path,
                'method' => $request->method()
            ]);
            
            $response = $this->gatewayRouter->routeRequest($service, $path, $request);
            
            \Log::info("GatewayRouter response received", [
                'status' => $response['status'],
                'data_keys' => array_keys($response['data'] ?? [])
            ]);
            
            return response()->json($response['data'], $response['status']);
        } catch (\Exception $e) {
            \Log::error("GatewayController exception", [
                'service' => $service,
                'path' => $path,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Service unavailable',
                'message' => $e->getMessage()
            ], 503);
        }
    }

    /**
     * Check if a route requires authentication.
     *
     * @param string $service
     * @param string $path
     * @return bool
     */
    protected function requiresAuthentication($service, $path)
    {
        // Public routes that don't require authentication
        $publicRoutes = [
            'auth' => ['login', 'register', 'refresh', 'validate-token'],
            'products' => ['*'], // All product routes are public (browsing catalog)
            'addresses' => ['countries'], // Public country/region data
            'messages' => ['api'], // Messages service health and stats
            'newsletters' => ['newsletters/subscribe', 'newsletters/confirm', 'newsletters/unsubscribe', 'health'], // Public newsletter routes
            'sav' => ['public', 'health'], // Public SAV routes for customer tickets
        ];

        // Check if service has any public routes
        if (!isset($publicRoutes[$service])) {
            return true; // Require auth for unknown services
        }

        // Get allowed routes for this service
        $allowedRoutes = $publicRoutes[$service];
        
        // If service allows all routes (*)
        if (in_array('*', $allowedRoutes)) {
            return false; // No authentication required
        }
        
        // Extract the action from path (e.g., 'countries' from 'countries/1/regions')
        $action = explode('/', trim($path, '/'))[0] ?? '';
        
        // Check if this specific action or path is public
        foreach ($allowedRoutes as $route) {
            if ($action === $route || strpos($path, $route) === 0) {
                return false; // No authentication required
            }
        }
        
        return true; // Authentication required
    }
}