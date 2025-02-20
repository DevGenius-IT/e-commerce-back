<?php

namespace App\Http\Controllers\API;

use Shared\Components\Controller;
use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
  /**
   * The authentication service.
   *
   * @var AuthService
   */
  protected $authService;

  public function __construct(AuthService $authService)
  {
    $this->authService = $authService;
  }

  /**
   * Authenticate a user.
   *
   * @param Request $request
   * @return \Illuminate\Http\JsonResponse
   */
  public function login(Request $request)
  {
    $credentials = $request->validate([
      "email" => "required|email",
      "password" => "required",
    ]);

    return response()->json($this->authService->login($credentials));
  }
}
