<?php

namespace Shared\Exceptions;

use Exception;
use Illuminate\Http\Response;

class ServiceUnavailableException extends Exception
{
  protected $service;
  protected $message;
  protected $code = Response::HTTP_SERVICE_UNAVAILABLE; // 503

  public function __construct($message = "Service unavailable", $service = null)
  {
    $this->message = $message;
    $this->service = $service;
    parent::__construct($message, $this->code);
  }

  public function getService()
  {
    return $this->service;
  }

  public function render()
  {
    return response()->json(
      [
        "error" => "Service Unavailable",
        "message" => $this->message,
        "service" => $this->service,
      ],
      $this->code
    );
  }
}
