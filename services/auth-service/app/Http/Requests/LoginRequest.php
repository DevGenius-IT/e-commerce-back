<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
  /**
   * Determine rules for the request.
   *
   * @return bool
   */
  public function rules()
  {
    return [
      "email" => "required|string|email",
      "password" => "required|string",
    ];
  }
}
