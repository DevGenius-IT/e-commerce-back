<?php

use Illuminate\Support\Facades\Route;

// Simple health check endpoint for Kubernetes probes
Route::get('health', function () {
    return response('healthy', 200)
        ->header('Content-Type', 'text/plain');
});

// Root route
Route::get('/', function () {
    return response()->json([
        'service' => 'api-gateway',
        'status' => 'running',
        'version' => '1.0.0'
    ]);
});