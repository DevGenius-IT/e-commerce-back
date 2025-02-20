<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Shared\Exceptions\ServiceUnavailableException;

class HttpClientService
{
  /**
   * The base URI for the service.
   *
   * @var string
   */
  protected $baseUri;

  /**
   * The secret key for the service.
   *
   * @var string
   */
  protected $secret;

  public function __construct(string $service)
  {
    $this->baseUri = config("services.{$service}.base_uri");
    $this->secret = config("services.{$service}.secret");
  }

  /**
   * Make a request to the service.
   *
   * @param string $method
   * @param string $endpoint
   * @param array $data
   * @return array
   * @throws ServiceUnavailableException
   */
  public function request($method, $endpoint, $data = [])
  {
    try {
      $response = Http::withHeaders([
        "X-Service-Key" => $this->secret,
        "Accept" => "application/json",
      ])->$method("{$this->baseUri}/api/{$endpoint}", $data);

      return $response->json();
    } catch (\Exception $e) {
      throw new ServiceUnavailableException("Service unavailable");
    }
  }

  /**
   * Make a GET request to the service.
   *
   * @param string $endpoint
   * @return array
   */
  public function get($endpoint)
  {
    return $this->request("get", $endpoint);
  }

  /**
   * Make a POST request to the service.
   *
   * @param string $endpoint
   * @param array $data
   * @return array
   */
  public function post($endpoint, $data)
  {
    return $this->request("post", $endpoint, $data);
  }

  /**
   * Make a PUT request to the service.
   *
   * @param string $endpoint
   * @param array $data
   * @return array
   */
  public function put($endpoint, $data)
  {
    return $this->request("put", $endpoint, $data);
  }

  /**
   * Make a DELETE request to the service.
   *
   * @param string $endpoint
   * @return array
   */
  public function delete($endpoint)
  {
    return $this->request("delete", $endpoint);
  }
}
