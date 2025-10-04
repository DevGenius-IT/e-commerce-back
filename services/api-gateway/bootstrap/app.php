<?php

use App\Http\Middleware\AuthenticateGateway;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    api: __DIR__ . "/../routes/api.php",
    apiPrefix: "api",
    commands: __DIR__ . "/../routes/console.php"
  )
  ->withMiddleware(function (Middleware $middleware) {
    // Disable TrustHosts middleware for Kubernetes environment
    // where requests come from various internal sources
    $middleware->alias([
      "auth.gateway" => AuthenticateGateway::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })
  ->create();
