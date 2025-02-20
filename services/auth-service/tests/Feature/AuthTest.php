<?php

namespace Tests\Feature;

use Tests\TestCase;
use Shared\Models\User;

class AuthTest extends TestCase
{
  public function test_user_can_register()
  {
    $response = $this->postJson("/api/v1/register", [
      "name" => "Test User",
      "email" => "test@example.com",
      "password" => "password123",
    ]);

    $response
      ->assertStatus(201)
      ->assertJsonStructure(["user", "token", "token_type", "expires_in"]);
  }

  public function test_user_can_login()
  {
    $user = User::factory()->create([
      "email" => "test@example.com",
      "password" => bcrypt("password123"),
    ]);

    $response = $this->postJson("/api/v1/login", [
      "email" => "test@example.com",
      "password" => "password123",
    ]);

    $response
      ->assertStatus(200)
      ->assertJsonStructure(["user", "token", "token_type", "expires_in"]);
  }
}
