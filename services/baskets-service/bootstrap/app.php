<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    api: __DIR__ . "/../routes/api.php",
    commands: __DIR__ . "/../routes/console.php"
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->trustHosts();
    
    // Register middleware aliases
    $middleware->alias([
      'jwt.auth' => \Shared\Middleware\JWTAuthMiddleware::class,
      'admin' => \App\Http\Middleware\AdminMiddleware::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })->create();