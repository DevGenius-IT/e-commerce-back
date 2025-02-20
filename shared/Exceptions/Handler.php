<?php

namespace Shared\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
  public function render($request, Throwable $exception)
  {
    if ($exception instanceof ServiceUnavailableException) {
      return response()->json(
        [
          "error" => "Service temporarily unavailable",
          "message" => $exception->getMessage(),
        ],
        503
      );
    }

    return parent::render($request, $exception);
  }
}
