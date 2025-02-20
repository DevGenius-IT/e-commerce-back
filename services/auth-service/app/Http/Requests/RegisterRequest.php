<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
  /**
   * Determine rules for the request.
   *
   * @return bool
   */
  public function rules()
  {
    return [
      "lastname" => "required|string|max:255",
      "firstname" => "required|string|max:255",
      "email" => "required|string|email|max:255|unique:users",
      "password" => "required|string|min:8",
      "role" => "sometimes|string|in:user,admin",
    ];
  }
}
