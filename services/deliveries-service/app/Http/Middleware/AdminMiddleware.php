<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Authentication required',
                'message' => 'User not authenticated'
            ], 401);
        }

        // Check if user email is in admin list
        $adminEmails = explode(',', env('ADMIN_EMAILS', ''));
        $adminEmails = array_map('trim', $adminEmails);
        
        if (!in_array($user->email, $adminEmails)) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Admin privileges required'
            ], 403);
        }

        return $next($request);
    }
}