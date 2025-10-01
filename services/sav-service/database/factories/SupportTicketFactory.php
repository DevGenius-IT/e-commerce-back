<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_number' => 'TKT-' . strtoupper($this->faker->unique()->bothify('??####')),
            'user_id' => $this->faker->numberBetween(1, 100),
            'subject' => $this->faker->sentence(6, true),
            'description' => $this->faker->paragraph(4),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => $this->faker->randomElement(['open', 'in_progress', 'waiting_customer', 'resolved', 'closed']),
            'category' => $this->faker->randomElement([
                'Technical Support',
                'Billing',
                'Product Information',
                'Delivery Issue',
                'Return/Refund',
                'Account Issue',
                'General Inquiry'
            ]),
            'assigned_to' => $this->faker->optional(0.6)->numberBetween(1, 10),
            'order_id' => $this->faker->optional(0.4)->numberBetween(1, 500),
            'metadata' => $this->faker->optional(0.3)->passthrough([
                'source' => $this->faker->randomElement(['web', 'mobile', 'phone', 'email']),
                'browser' => $this->faker->optional()->userAgent(),
                'ip_address' => $this->faker->optional()->ipv4(),
            ]),
            'resolved_at' => function (array $attributes) {
                return in_array($attributes['status'], ['resolved', 'closed']) 
                    ? $this->faker->dateTimeBetween('-30 days', 'now')
                    : null;
            },
            'closed_at' => function (array $attributes) {
                return $attributes['status'] === 'closed'
                    ? $this->faker->dateTimeBetween($attributes['resolved_at'] ?? '-30 days', 'now')
                    : null;
            },
        ];
    }

    /**
     * Indicate that the ticket is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'assigned_to' => $this->faker->numberBetween(1, 10),
            'resolved_at' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'assigned_to' => $this->faker->numberBetween(1, 10),
            'resolved_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'closed_at' => null,
        ]);
    }

    /**
     * Indicate that the ticket is closed.
     */
    public function closed(): static
    {
        $resolvedAt = $this->faker->dateTimeBetween('-30 days', '-1 day');
        
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'assigned_to' => $this->faker->numberBetween(1, 10),
            'resolved_at' => $resolvedAt,
            'closed_at' => $this->faker->dateTimeBetween($resolvedAt, 'now'),
        ]);
    }

    /**
     * Indicate that the ticket is urgent.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'urgent',
        ]);
    }

    /**
     * Indicate that the ticket is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    /**
     * Indicate that the ticket has an order associated.
     */
    public function withOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $this->faker->numberBetween(1, 500),
            'category' => $this->faker->randomElement(['Delivery Issue', 'Return/Refund', 'Product Information']),
        ]);
    }
}