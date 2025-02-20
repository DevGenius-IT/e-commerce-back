<?php

namespace App\Services;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Shared\Exceptions\AuthenticationException;

class AuthService
{
  /**
   * The user repository instance.
   *
   * @var UserRepositoryInterface
   */
  protected $userRepository;

  public function __construct(UserRepositoryInterface $userRepository)
  {
    $this->userRepository = $userRepository;
  }

  /**
   * Register a new user.
   *
   * @param array<string, string> $data
   * @return array<string, mixed>
   */
  public function register(array $data)
  {
    $user = $this->userRepository->store($data);
    $token = JWTAuth::fromUser($user);

    return [
      "user" => $user,
      "token" => $token,
      "token_type" => "bearer",
      "expires_in" => config("jwt.ttl") * 60,
    ];
  }

  /**
   * Login a user.
   *
   * @param array<string, string> $credentials
   * @return array<string, mixed>
   */
  public function login(array $credentials)
  {
    $user = $this->userRepository->findByEmail($credentials["email"]);

    if (!$user || !Hash::check($credentials["password"], $user->password)) {
      throw new AuthenticationException("Invalid credentials");
    }

    $token = JWTAuth::fromUser($user);

    return [
      "user" => $user,
      "token" => $token,
      "token_type" => "bearer",
      "expires_in" => config("jwt.ttl") * 60,
    ];
  }

  /**
   * Validate a token.
   *
   * @param string $token
   * @return array<string, mixed>
   */
  public function validateToken(string $token)
  {
    try {
      $user = JWTAuth::parseToken()->authenticate();
      return [
        "valid" => true,
        "user" => $user,
      ];
    } catch (\Exception $e) {
      return [
        "valid" => false,
        "message" => "Invalid token",
      ];
    }
  }

  /**
   * Refresh a token.
   *
   * @return array<string, mixed>
   */
  public function refreshToken()
  {
    try {
      $token = JWTAuth::parseToken()->refresh();
      return [
        "token" => $token,
        "token_type" => "bearer",
        "expires_in" => config("jwt.ttl") * 60,
      ];
    } catch (\Exception $e) {
      throw new AuthenticationException("Could not refresh token");
    }
  }

  /**
   * Logout a user.
   *
   * @return bool
   */
  public function logout()
  {
    try {
      JWTAuth::parseToken()->invalidate();
      return true;
    } catch (\Exception $e) {
      throw new AuthenticationException("Could not logout user");
    }
  }
}
