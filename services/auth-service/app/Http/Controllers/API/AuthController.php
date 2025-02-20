<?php

namespace App\Http\Controllers\API;

use Shared\Components\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
  /**
   * The auth service instance.
   *
   * @var AuthService
   */
  protected $authService;

  public function __construct(AuthService $authService)
  {
    $this->authService = $authService;
    $this->middleware("auth:api", ["except" => ["login", "register", "validateToken"]]);
  }

  /**
   * Register a new user.
   *
   * @param RegisterRequest $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function register(RegisterRequest $request)
  {
    $result = $this->authService->register($request->validated());
    return response()->json($result, 201);
  }

  /**
   * Login a user.
   *
   * @param LoginRequest $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function login(LoginRequest $request)
  {
    $result = $this->authService->login($request->validated());
    return response()->json($result);
  }

  /**
   * Validate a token.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function validateToken(Request $request)
  {
    $result = $this->authService->validateToken($request->token);
    return response()->json($result);
  }

  /**
   * Refresh a token.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function refresh()
  {
    $result = $this->authService->refreshToken();
    return response()->json($result);
  }

  /**
   * Logout a user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function logout()
  {
    $this->authService->logout();
    return response()->json(["message" => "Successfully logged out"]);
  }

  /**
   * Get the authenticated user.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function me()
  {
    return response()->json(auth()->user());
  }
}
