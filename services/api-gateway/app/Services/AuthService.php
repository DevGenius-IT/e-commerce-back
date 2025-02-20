<?php

namespace App\Services;

class AuthService
{
  /**
   * The HTTP client service.
   *
   * @var HttpClientService
   */
  protected $httpClient;

  public function __construct()
  {
    $this->httpClient = new HttpClientService("auth");
  }

  /**
   * Authenticate a user.
   *
   * @param array $data
   * @return array
   */
  public function login($credentials)
  {
    return $this->httpClient->post("login", $credentials);
  }

  /**
   * Check if a token is valid.
   *
   * @param string $token
   * @return array
   */
  public function validateToken($token)
  {
    return $this->httpClient->post("validate-token", ["token" => $token]);
  }
}
