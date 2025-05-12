<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\Authenticate;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    api: __DIR__ . "/../routes/api.php",
    commands: __DIR__ . "/../routes/console.php"
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->trustHosts();
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })->create();
