<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'You must be authenticated to access this resource'
            ], 401);
        }

        // For now, we'll check if the user email is an admin email
        // In a full implementation, this should check roles/permissions
        $adminEmails = ['admin@flippad.com', 'kylian@collect-verything.com'];
        
        if (!in_array($user->email, $adminEmails)) {
            return response()->json([
                'error' => 'Access forbidden',
                'message' => 'You do not have permission to access this resource'
            ], 403);
        }

        return $next($request);
    }
}