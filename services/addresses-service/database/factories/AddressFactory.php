<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'type' => fake()->randomElement(['billing', 'shipping', 'both']),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'company' => fake()->optional(0.3)->company(),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.4)->secondaryAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'region_id' => Region::factory(),
            'country_id' => Country::factory(),
            'phone' => fake()->optional(0.7)->phoneNumber(),
            'is_default' => false,
            'latitude' => fake()->optional(0.3)->latitude(),
            'longitude' => fake()->optional(0.3)->longitude(),
        ];
    }

    /**
     * Indicate that the address is the default address.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that this is a billing address.
     */
    public function billing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_BILLING,
        ]);
    }

    /**
     * Indicate that this is a shipping address.
     */
    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Address::TYPE_SHIPPING,
        ]);
    }
}