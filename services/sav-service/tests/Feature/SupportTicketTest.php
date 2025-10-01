<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_create_support_ticket()
    {
        $ticketData = [
            'user_id' => 1,
            'subject' => 'Test ticket subject',
            'description' => 'This is a test ticket description.',
            'priority' => 'medium',
            'category' => 'Technical Support',
        ];

        $response = $this->postJson('/api/tickets', $ticketData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'ticket_number',
                        'user_id',
                        'subject',
                        'description',
                        'priority',
                        'status',
                        'category',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        $this->assertDatabaseHas('support_tickets', [
            'user_id' => 1,
            'subject' => 'Test ticket subject',
            'status' => 'open'
        ]);
    }

    public function test_can_list_support_tickets()
    {
        SupportTicket::factory(3)->create();

        $response = $this->getJson('/api/tickets');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'ticket_number',
                            'subject',
                            'status',
                            'priority',
                            'created_at'
                        ]
                    ],
                    'pagination'
                ]);
    }

    public function test_can_show_specific_ticket()
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->getJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'ticket_number',
                        'subject',
                        'description',
                        'status',
                        'priority',
                        'messages',
                        'attachments'
                    ]
                ]);
    }

    public function test_can_update_ticket()
    {
        $ticket = SupportTicket::factory()->create([
            'status' => 'open',
            'priority' => 'low'
        ]);

        $updateData = [
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => 5
        ];

        $response = $this->putJson("/api/tickets/{$ticket->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Support ticket updated successfully'
                ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to' => 5
        ]);
    }

    public function test_can_assign_ticket()
    {
        $ticket = SupportTicket::factory()->create(['status' => 'open']);

        $response = $this->postJson("/api/tickets/{$ticket->id}/assign", [
            'assigned_to' => 3
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Ticket assigned successfully'
                ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'assigned_to' => 3,
            'status' => 'in_progress'
        ]);
    }

    public function test_can_resolve_ticket()
    {
        $ticket = SupportTicket::factory()->create([
            'status' => 'in_progress',
            'assigned_to' => 2
        ]);

        $response = $this->postJson("/api/tickets/{$ticket->id}/resolve");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Ticket marked as resolved'
                ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'resolved'
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_can_close_ticket()
    {
        $ticket = SupportTicket::factory()->create([
            'status' => 'resolved',
            'resolved_at' => now()->subHours(2)
        ]);

        $response = $this->postJson("/api/tickets/{$ticket->id}/close");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Ticket closed successfully'
                ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'closed'
        ]);

        $ticket->refresh();
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_can_delete_ticket()
    {
        $ticket = SupportTicket::factory()->create();

        $response = $this->deleteJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Support ticket deleted successfully'
                ]);

        $this->assertDatabaseMissing('support_tickets', ['id' => $ticket->id]);
    }

    public function test_can_filter_tickets_by_status()
    {
        SupportTicket::factory()->create(['status' => 'open']);
        SupportTicket::factory()->create(['status' => 'closed']);
        SupportTicket::factory()->create(['status' => 'open']);

        $response = $this->getJson('/api/tickets?status=open');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        
        foreach ($data as $ticket) {
            $this->assertEquals('open', $ticket['status']);
        }
    }

    public function test_can_search_tickets()
    {
        SupportTicket::factory()->create(['subject' => 'Login issue with my account']);
        SupportTicket::factory()->create(['subject' => 'Payment problem']);
        SupportTicket::factory()->create(['description' => 'Cannot login to my account']);

        $response = $this->getJson('/api/tickets?search=login');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_can_get_statistics()
    {
        SupportTicket::factory(3)->create(['status' => 'open']);
        SupportTicket::factory(2)->create(['status' => 'closed']);
        SupportTicket::factory(1)->create(['priority' => 'urgent']);

        $response = $this->getJson('/api/tickets/statistics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'total_tickets',
                        'open_tickets',
                        'closed_tickets',
                        'urgent_tickets'
                    ]
                ]);

        $stats = $response->json('data');
        $this->assertEquals(6, $stats['total_tickets']);
        $this->assertEquals(3, $stats['open_tickets']);
        $this->assertEquals(2, $stats['closed_tickets']);
        $this->assertGreaterThanOrEqual(1, $stats['urgent_tickets']);
    }

    public function test_ticket_validation()
    {
        $response = $this->postJson('/api/tickets', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id', 'subject', 'description']);
    }

    public function test_ticket_not_found()
    {
        $response = $this->getJson('/api/tickets/999');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Support ticket not found'
                ]);
    }

    public function test_ticket_number_is_unique()
    {
        $ticket1 = SupportTicket::factory()->create();
        $ticket2 = SupportTicket::factory()->create();

        $this->assertNotEquals($ticket1->ticket_number, $ticket2->ticket_number);
        $this->assertStringStartsWith('TKT-', $ticket1->ticket_number);
        $this->assertStringStartsWith('TKT-', $ticket2->ticket_number);
    }
}