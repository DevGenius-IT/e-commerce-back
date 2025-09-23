<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test country and region
        $this->country = Country::create([
            'name' => 'Test Country',
            'code' => 'TC',
            'iso3' => 'TST',
            'phone_code' => '+1',
            'is_active' => true
        ]);

        $this->region = Region::create([
            'name' => 'Test Region',
            'code' => 'TR',
            'country_id' => $this->country->id,
            'is_active' => true
        ]);
    }

    public function test_can_list_addresses()
    {
        // Create test addresses
        Address::factory()->count(3)->create([
            'user_id' => 1,
            'country_id' => $this->country->id,
            'region_id' => $this->region->id
        ]);

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->getJson('/api/addresses');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id', 'type', 'first_name', 'last_name', 
                            'address_line_1', 'city', 'postal_code',
                            'country', 'region'
                        ]
                    ],
                    'meta' => ['total']
                ]);
    }

    public function test_can_create_address()
    {
        $addressData = [
            'type' => 'both',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'address_line_1' => '123 Main St',
            'city' => 'Test City',
            'postal_code' => '12345',
            'country_id' => $this->country->id,
            'region_id' => $this->region->id,
            'phone' => '+1234567890',
            'is_default' => true
        ];

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->postJson('/api/addresses', $addressData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id', 'type', 'first_name', 'last_name', 
                        'address_line_1', 'city', 'postal_code',
                        'is_default', 'country', 'region'
                    ],
                    'message'
                ]);

        $this->assertDatabaseHas('addresses', [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'is_default' => true
        ]);
    }

    public function test_can_update_address()
    {
        $address = Address::factory()->create([
            'user_id' => 1,
            'country_id' => $this->country->id,
            'region_id' => $this->region->id,
            'first_name' => 'Old Name'
        ]);

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->putJson("/api/addresses/{$address->id}", [
            'first_name' => 'New Name'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'data' => ['first_name' => 'New Name'],
                    'message' => 'Address updated successfully'
                ]);
    }

    public function test_can_delete_address()
    {
        $address = Address::factory()->create([
            'user_id' => 1,
            'country_id' => $this->country->id,
            'region_id' => $this->region->id
        ]);

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->deleteJson("/api/addresses/{$address->id}");

        $response->assertStatus(200)
                ->assertJson(['message' => 'Address deleted successfully']);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_can_set_default_address()
    {
        $address1 = Address::factory()->create([
            'user_id' => 1,
            'country_id' => $this->country->id,
            'is_default' => true
        ]);

        $address2 = Address::factory()->create([
            'user_id' => 1,
            'country_id' => $this->country->id,
            'is_default' => false
        ]);

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->postJson("/api/addresses/{$address2->id}/set-default");

        $response->assertStatus(200)
                ->assertJson([
                    'data' => ['is_default' => true],
                    'message' => 'Address set as default successfully'
                ]);

        // Check that previous default is no longer default
        $this->assertDatabaseHas('addresses', ['id' => $address1->id, 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $address2->id, 'is_default' => true]);
    }

    public function test_cannot_access_other_users_addresses()
    {
        $address = Address::factory()->create([
            'user_id' => 2, // Different user
            'country_id' => $this->country->id
        ]);

        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1])) // User 1 trying to access user 2's address
        ])->getJson("/api/addresses/{$address->id}");

        $response->assertStatus(404);
    }

    public function test_validates_address_creation()
    {
        $response = $this->withHeaders([
            'X-Auth-User' => base64_encode(json_encode(['id' => 1]))
        ])->postJson('/api/addresses', [
            // Missing required fields
            'type' => 'invalid_type',
            'country_id' => 999999 // Non-existent country
        ]);

        $response->assertStatus(422);
    }
}