<?php

namespace Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shared\Exceptions\AuthenticationException;
use Shared\Models\User;

class JWTAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'Token not provided'
            ], 401);
        }

        try {
            $user = $this->validateTokenAndGetUser($token);
            
            if (!$user) {
                return response()->json([
                    'error' => 'Authentication failed',
                    'message' => 'Invalid token'
                ], 401);
            }

            // Set the authenticated user in the request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            return $next($request);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed',
                'message' => 'Token validation error: ' . $e->getMessage()
            ], 401);
        }
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        return substr($authHeader, 7);
    }

    /**
     * Validate token and get user
     * This can be enhanced to make a call to auth service
     */
    private function validateTokenAndGetUser(string $token): ?User
    {
        try {
            // For now, we'll validate the token structure
            // In a full implementation, this should call the auth service
            $payload = $this->decodeJWT($token);
            
            if (!$payload) {
                return null;
            }

            // Support both user_id and sub fields for user identification
            $userId = $payload['user_id'] ?? $payload['sub'] ?? null;
            if (!$userId) {
                return null;
            }

            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return null;
            }

            // Create a minimal user object for the request
            $user = new User();
            $user->id = $userId;
            $user->email = $payload['email'] ?? '';
            
            return $user;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Simple JWT decode (for demo purposes)
     * In production, this should properly validate with the auth service
     */
    private function decodeJWT(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            
            return $payload;

        } catch (\Exception $e) {
            return null;
        }
    }
}