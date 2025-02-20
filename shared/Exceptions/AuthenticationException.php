<?php

namespace Shared\Exceptions;

use Exception;

class AuthenticationException extends Exception
{
  /**
   * The exception message.
   *
   * @var string
   */
  protected $message;
  
  /**
   * The exception code.
   *
   * @var int
   */
  protected $code = 401;

  public function __construct($message = "Unauthorized")
  {
    $this->message = $message;
  }

  /**
   * Render the exception into an HTTP response.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function render()
  {
    return response()->json(
      [
        "error" => "Authentication failed",
        "message" => $this->message,
      ],
      $this->code
    );
  }
}
