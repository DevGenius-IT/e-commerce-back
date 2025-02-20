<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProductGatewayTest extends TestCase
{
  /**
   * Test if a user can get products with a valid token.
   *
   * @return void
   */
  public function test_can_get_products_with_valid_token()
  {
    $token = "valid_token"; // Mock token

    $response = $this->withHeaders([
      "Authorization" => "Bearer " . $token,
    ])->getJson("/api/v1/products");

    $response->assertStatus(200)->assertJsonStructure([
      "data" => [
        "*" => ["id", "name", "price"],
      ],
    ]);
  }
}
