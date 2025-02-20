<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\AuthService;

class AuthenticateGateway
{
  /**
   * The authentication service.
   *
   * @var AuthService
   */
  protected $authService;

  /**
   * Create a new middleware instance.
   *
   * @param  AuthService  $authService
   * @return void
   */
  public function __construct(AuthService $authService)
  {
    $this->authService = $authService;
  }

  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle($request, Closure $next)
  {
    $token = $request->bearerToken();

    if (!$token) {
      return response()->json(["error" => "Unauthorized"], 401);
    }

    try {
      $validation = $this->authService->validateToken($token);
      if (!$validation["valid"]) {
        return response()->json(["error" => "Invalid token"], 401);
      }

      $request->attributes->add(["user" => $validation["user"]]);
      return $next($request);
    } catch (\Exception $e) {
      return response()->json(["error" => "Authentication service unavailable"], 503);
    }
  }
}
