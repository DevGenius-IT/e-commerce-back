<?php

namespace Database\Factories;

use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Newsletter>
 */
class NewsletterFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Newsletter::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'status' => $this->faker->randomElement(['subscribed', 'unsubscribed', 'pending']),
            'preferences' => $this->faker->optional()->randomElements([
                'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
                'topics' => $this->faker->randomElements(['tech', 'business', 'lifestyle', 'health'], rand(1, 3)),
                'format' => $this->faker->randomElement(['html', 'text']),
            ]),
            'subscription_source' => $this->faker->randomElement(['website', 'checkout', 'api', 'import', 'social_media']),
            'unsubscribe_token' => Str::random(64),
            'bounce_count' => $this->faker->numberBetween(0, 5),
            'subscribed_at' => $this->faker->optional(0.8)->dateTimeBetween('-2 years', 'now'),
            'unsubscribed_at' => $this->faker->optional(0.1)->dateTimeBetween('-1 year', 'now'),
            'last_bounce_at' => $this->faker->optional(0.1)->dateTimeBetween('-6 months', 'now'),
            'notes' => $this->faker->optional()->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the newsletter is subscribed.
     */
    public function subscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'subscribed',
            'subscribed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'unsubscribed_at' => null,
        ]);
    }

    /**
     * Indicate that the newsletter is unsubscribed.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'unsubscribed',
            'subscribed_at' => $this->faker->dateTimeBetween('-2 years', '-1 month'),
            'unsubscribed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the newsletter is pending confirmation.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'subscribed_at' => null,
            'unsubscribed_at' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the newsletter has bounced.
     */
    public function bounced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'bounced',
            'bounce_count' => $this->faker->numberBetween(3, 10),
            'last_bounce_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'notes' => 'Email bounced multiple times - ' . $this->faker->sentence(),
        ]);
    }

    /**
     * Indicate that the newsletter is from a specific source.
     */
    public function fromSource(string $source): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_source' => $source,
        ]);
    }

    /**
     * Indicate that the newsletter has specific preferences.
     */
    public function withPreferences(array $preferences): static
    {
        return $this->state(fn (array $attributes) => [
            'preferences' => $preferences,
        ]);
    }
}