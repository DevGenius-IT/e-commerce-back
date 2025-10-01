<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_countries()
    {
        // Create active and inactive countries
        Country::factory()->count(3)->create();
        Country::factory()->inactive()->create();

        $response = $this->getJson('/api/countries');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'code', 'iso3', 'phone_code', 'is_active']
                    ],
                    'meta' => ['total']
                ])
                ->assertJsonCount(3, 'data'); // Only active countries
    }

    public function test_can_search_countries()
    {
        Country::factory()->create(['name' => 'United States', 'code' => 'US']);
        Country::factory()->create(['name' => 'Canada', 'code' => 'CA']);
        Country::factory()->create(['name' => 'France', 'code' => 'FR']);

        $response = $this->getJson('/api/countries?search=United');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'United States');
    }

    public function test_can_get_country_with_regions()
    {
        $country = Country::factory()->create();
        Region::factory()->count(3)->create(['country_id' => $country->id]);

        $response = $this->getJson("/api/countries/{$country->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'id', 'name', 'code', 'regions' => [
                            '*' => ['id', 'name', 'code', 'country_id']
                        ]
                    ]
                ])
                ->assertJsonCount(3, 'data.regions');
    }

    public function test_can_get_regions_for_country()
    {
        $country = Country::factory()->create();
        Region::factory()->count(2)->create(['country_id' => $country->id]);
        Region::factory()->inactive()->create(['country_id' => $country->id]); // Inactive region

        $response = $this->getJson("/api/countries/{$country->id}/regions");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        '*' => ['id', 'name', 'code', 'country_id', 'is_active']
                    ],
                    'meta' => ['total', 'country']
                ])
                ->assertJsonCount(2, 'data'); // Only active regions
    }

    public function test_cannot_get_inactive_country()
    {
        $country = Country::factory()->inactive()->create();

        $response = $this->getJson("/api/countries/{$country->id}");

        $response->assertStatus(404);
    }

    public function test_cannot_get_regions_for_inactive_country()
    {
        $country = Country::factory()->inactive()->create();

        $response = $this->getJson("/api/countries/{$country->id}/regions");

        $response->assertStatus(404);
    }
}