<?php

namespace Database\Seeders;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\TicketAttachment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupportTicketSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Nettoyer les tables
        TicketAttachment::truncate();
        TicketMessage::truncate();
        SupportTicket::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Créer différents types de tickets
        
        // 1. Tickets ouverts (20 tickets)
        $openTickets = SupportTicket::factory(20)->open()->create();
        
        // 2. Tickets en cours (15 tickets)
        $inProgressTickets = SupportTicket::factory(15)->inProgress()->create();
        
        // 3. Tickets résolus (25 tickets)
        $resolvedTickets = SupportTicket::factory(25)->resolved()->create();
        
        // 4. Tickets fermés (30 tickets)
        $closedTickets = SupportTicket::factory(30)->closed()->create();
        
        // 5. Tickets urgents (5 tickets)
        $urgentTickets = SupportTicket::factory(5)->urgent()->open()->create();
        
        // 6. Tickets avec commande (10 tickets)
        $orderTickets = SupportTicket::factory(10)->withOrder()->inProgress()->create();

        // Ajouter des messages pour tous les tickets
        $allTickets = collect([
            ...$openTickets,
            ...$inProgressTickets,
            ...$resolvedTickets,
            ...$closedTickets,
            ...$urgentTickets,
            ...$orderTickets,
        ]);

        foreach ($allTickets as $ticket) {
            // Message initial du client
            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_id' => $ticket->user_id,
                'sender_type' => 'customer',
                'message' => $ticket->description,
                'is_internal' => false,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->created_at,
            ]);

            // Messages additionnels selon le statut
            if (in_array($ticket->status, ['in_progress', 'resolved', 'closed'])) {
                // Réponse de l'agent
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => $ticket->assigned_to ?? 1,
                    'sender_type' => 'agent',
                    'message' => $this->getAgentResponseMessage($ticket->category),
                    'is_internal' => false,
                    'created_at' => $ticket->created_at->addHours(2),
                    'updated_at' => $ticket->created_at->addHours(2),
                ]);

                // Parfois une réponse du client
                if (fake()->boolean(60)) {
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'sender_id' => $ticket->user_id,
                        'sender_type' => 'customer',
                        'message' => $this->getCustomerFollowUpMessage(),
                        'is_internal' => false,
                        'created_at' => $ticket->created_at->addHours(6),
                        'updated_at' => $ticket->created_at->addHours(6),
                    ]);
                }

                // Note interne de l'agent
                if (fake()->boolean(40)) {
                    TicketMessage::create([
                        'ticket_id' => $ticket->id,
                        'sender_id' => $ticket->assigned_to ?? 1,
                        'sender_type' => 'agent',
                        'message' => $this->getInternalNote(),
                        'is_internal' => true,
                        'created_at' => $ticket->created_at->addHours(1),
                        'updated_at' => $ticket->created_at->addHours(1),
                    ]);
                }
            }

            // Message de résolution pour les tickets résolus/fermés
            if (in_array($ticket->status, ['resolved', 'closed']) && $ticket->resolved_at) {
                TicketMessage::create([
                    'ticket_id' => $ticket->id,
                    'sender_id' => $ticket->assigned_to ?? 1,
                    'sender_type' => 'agent',
                    'message' => $this->getResolutionMessage($ticket->category),
                    'is_internal' => false,
                    'created_at' => $ticket->resolved_at->subMinutes(30),
                    'updated_at' => $ticket->resolved_at->subMinutes(30),
                ]);
            }
        }

        $this->command->info('SAV Tickets seeded successfully!');
        $this->command->info('- Open tickets: 20');
        $this->command->info('- In progress tickets: 15');
        $this->command->info('- Resolved tickets: 25');
        $this->command->info('- Closed tickets: 30');
        $this->command->info('- Urgent tickets: 5');
        $this->command->info('- Order-related tickets: 10');
        $this->command->info('Total: ' . $allTickets->count() . ' tickets with messages');
    }

    private function getAgentResponseMessage(string $category): string
    {
        $responses = [
            'Technical Support' => [
                'Thank you for contacting our technical support. I\'m looking into your issue.',
                'I\'ve reviewed your request and I\'m working on a solution.',
                'Let me investigate this technical issue for you.',
            ],
            'Billing' => [
                'I\'ve received your billing inquiry and I\'m reviewing your account.',
                'Thank you for your billing question. I\'m checking this with our billing department.',
                'I\'m looking into your billing concern right now.',
            ],
            'Product Information' => [
                'Thank you for your product inquiry. Let me get you the information you need.',
                'I\'m happy to help with your product questions.',
                'I\'ll provide you with detailed product information shortly.',
            ],
            'Delivery Issue' => [
                'I\'m sorry to hear about the delivery issue. Let me track your package.',
                'I\'m investigating your delivery concern right away.',
                'Thank you for reporting this delivery issue. I\'m on it.',
            ],
            'Return/Refund' => [
                'I\'ve received your return/refund request and I\'m processing it.',
                'Thank you for your return request. I\'m reviewing the details.',
                'I\'m handling your refund request and will update you soon.',
            ],
        ];

        $categoryResponses = $responses[$category] ?? $responses['Technical Support'];
        return fake()->randomElement($categoryResponses);
    }

    private function getCustomerFollowUpMessage(): string
    {
        $messages = [
            'Thank you for your quick response!',
            'I appreciate your help with this issue.',
            'When can I expect an update?',
            'Is there anything else I need to provide?',
            'Thank you for looking into this.',
            'I\'m still waiting for a resolution.',
            'Could you please provide more details?',
        ];

        return fake()->randomElement($messages);
    }

    private function getInternalNote(): string
    {
        $notes = [
            'Customer contacted via phone as well. High priority.',
            'Escalating to senior team member.',
            'Checking with product team for technical details.',
            'Customer is a premium member - prioritize.',
            'Similar issue reported by other customers.',
            'Coordinating with billing department.',
            'Awaiting response from logistics team.',
        ];

        return fake()->randomElement($notes);
    }

    private function getResolutionMessage(string $category): string
    {
        $resolutions = [
            'Technical Support' => [
                'The technical issue has been resolved. Please test and let us know if you need further assistance.',
                'I\'ve fixed the technical problem. The solution is now active on your account.',
                'The technical issue has been addressed. You should see the improvements immediately.',
            ],
            'Billing' => [
                'Your billing issue has been resolved. The correction will appear on your next statement.',
                'I\'ve corrected the billing error. You should see the updated information in your account.',
                'The billing issue has been fixed and your account has been updated accordingly.',
            ],
            'Product Information' => [
                'I\'ve provided all the product information you requested. Please let me know if you need anything else.',
                'Your product inquiry has been fully addressed. Feel free to contact us for any additional questions.',
            ],
            'Delivery Issue' => [
                'Your delivery issue has been resolved. The package has been located and will be delivered shortly.',
                'I\'ve resolved the delivery problem. You should receive your order within 24 hours.',
                'The delivery issue has been fixed. Your package is now on its way.',
            ],
            'Return/Refund' => [
                'Your return has been processed and the refund will appear in 3-5 business days.',
                'I\'ve completed your refund request. The amount will be credited to your original payment method.',
                'Your return has been approved and processed. You should see the refund soon.',
            ],
        ];

        $categoryResolutions = $resolutions[$category] ?? ['Your issue has been resolved. Thank you for your patience.'];
        return fake()->randomElement($categoryResolutions);
    }
}