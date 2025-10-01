<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TicketMessageTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_create_message_for_ticket()
    {
        $ticket = SupportTicket::factory()->create();

        $messageData = [
            'sender_id' => 1,
            'sender_type' => 'customer',
            'message' => 'This is a test message for the ticket.',
            'is_internal' => false,
        ];

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", $messageData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'ticket_id',
                        'sender_id',
                        'sender_type',
                        'message',
                        'is_internal',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'sender_id' => 1,
            'sender_type' => 'customer',
            'message' => 'This is a test message for the ticket.'
        ]);
    }

    public function test_can_list_messages_for_ticket()
    {
        $ticket = SupportTicket::factory()->create();
        TicketMessage::factory(3)->create(['ticket_id' => $ticket->id]);

        $response = $this->getJson("/api/tickets/{$ticket->id}/messages");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'ticket_id',
                            'sender_id',
                            'sender_type',
                            'message',
                            'is_internal',
                            'created_at'
                        ]
                    ]
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_show_specific_message()
    {
        $ticket = SupportTicket::factory()->create();
        $message = TicketMessage::factory()->create(['ticket_id' => $ticket->id]);

        $response = $this->getJson("/api/tickets/{$ticket->id}/messages/{$message->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'ticket_id',
                        'sender_id',
                        'sender_type',
                        'message',
                        'is_internal'
                    ]
                ]);
    }

    public function test_can_update_message()
    {
        $ticket = SupportTicket::factory()->create();
        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'message' => 'Original message',
            'is_internal' => false
        ]);

        $updateData = [
            'message' => 'Updated message content',
            'is_internal' => true
        ];

        $response = $this->putJson("/api/tickets/{$ticket->id}/messages/{$message->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Message updated successfully'
                ]);

        $this->assertDatabaseHas('ticket_messages', [
            'id' => $message->id,
            'message' => 'Updated message content',
            'is_internal' => true
        ]);
    }

    public function test_can_delete_message()
    {
        $ticket = SupportTicket::factory()->create();
        $message = TicketMessage::factory()->create(['ticket_id' => $ticket->id]);

        $response = $this->deleteJson("/api/tickets/{$ticket->id}/messages/{$message->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Message deleted successfully'
                ]);

        $this->assertDatabaseMissing('ticket_messages', ['id' => $message->id]);
    }

    public function test_can_mark_message_as_read()
    {
        $ticket = SupportTicket::factory()->create();
        $message = TicketMessage::factory()->create([
            'ticket_id' => $ticket->id,
            'read_at' => null
        ]);

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages/{$message->id}/mark-read");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Message marked as read'
                ]);

        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    public function test_can_mark_all_messages_as_read()
    {
        $ticket = SupportTicket::factory()->create();
        $messages = TicketMessage::factory(3)->create([
            'ticket_id' => $ticket->id,
            'read_at' => null
        ]);

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages/mark-all-read");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'All messages marked as read'
                ]);

        foreach ($messages as $message) {
            $message->refresh();
            $this->assertNotNull($message->read_at);
        }
    }

    public function test_can_get_unread_count()
    {
        $ticket = SupportTicket::factory()->create();
        
        // Créer des messages lus et non lus
        TicketMessage::factory(2)->create([
            'ticket_id' => $ticket->id,
            'read_at' => now()
        ]);
        
        TicketMessage::factory(3)->create([
            'ticket_id' => $ticket->id,
            'read_at' => null
        ]);

        $response = $this->getJson("/api/tickets/{$ticket->id}/messages/unread-count");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'unread_count' => 3
                    ]
                ]);
    }

    public function test_customer_message_updates_waiting_ticket_status()
    {
        $ticket = SupportTicket::factory()->create([
            'status' => 'waiting_customer'
        ]);

        $messageData = [
            'sender_id' => $ticket->user_id,
            'sender_type' => 'customer',
            'message' => 'Customer response to waiting ticket.',
            'is_internal' => false,
        ];

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", $messageData);

        $response->assertStatus(201);

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
    }

    public function test_agent_message_does_not_change_waiting_status()
    {
        $ticket = SupportTicket::factory()->create([
            'status' => 'waiting_customer'
        ]);

        $messageData = [
            'sender_id' => 2,
            'sender_type' => 'agent',
            'message' => 'Agent message to waiting ticket.',
            'is_internal' => false,
        ];

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", $messageData);

        $response->assertStatus(201);

        $ticket->refresh();
        $this->assertEquals('waiting_customer', $ticket->status);
    }

    public function test_message_validation()
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['sender_id', 'sender_type', 'message']);
    }

    public function test_message_invalid_sender_type()
    {
        $ticket = SupportTicket::factory()->create();

        $messageData = [
            'sender_id' => 1,
            'sender_type' => 'invalid_type',
            'message' => 'Test message',
        ];

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", $messageData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['sender_type']);
    }

    public function test_message_for_nonexistent_ticket()
    {
        $messageData = [
            'sender_id' => 1,
            'sender_type' => 'customer',
            'message' => 'Test message',
        ];

        $response = $this->postJson("/api/tickets/999/messages", $messageData);

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Support ticket not found'
                ]);
    }

    public function test_internal_message_creation()
    {
        $ticket = SupportTicket::factory()->create();

        $messageData = [
            'sender_id' => 2,
            'sender_type' => 'agent',
            'message' => 'Internal note about this ticket.',
            'is_internal' => true,
        ];

        $response = $this->postJson("/api/tickets/{$ticket->id}/messages", $messageData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ticket_messages', [
            'ticket_id' => $ticket->id,
            'message' => 'Internal note about this ticket.',
            'is_internal' => true,
            'sender_type' => 'agent'
        ]);
    }
}