<?php

namespace Database\Factories;

use App\Models\TicketMessage;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TicketMessage>
 */
class TicketMessageFactory extends Factory
{
    protected $model = TicketMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $senderType = $this->faker->randomElement(['customer', 'agent']);
        
        return [
            'ticket_id' => SupportTicket::factory(),
            'sender_id' => $this->faker->numberBetween(1, 100),
            'sender_type' => $senderType,
            'message' => $this->faker->paragraph(3),
            'is_internal' => $senderType === 'agent' ? $this->faker->boolean(30) : false,
            'read_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Indicate that the message is from a customer.
     */
    public function fromCustomer(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'customer',
            'is_internal' => false,
        ]);
    }

    /**
     * Indicate that the message is from an agent.
     */
    public function fromAgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'agent',
        ]);
    }

    /**
     * Indicate that the message is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'sender_type' => 'agent',
            'is_internal' => true,
        ]);
    }

    /**
     * Indicate that the message is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Indicate that the message is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}