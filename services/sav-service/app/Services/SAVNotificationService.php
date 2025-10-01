<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Shared\Services\RabbitMQClientService;
use Illuminate\Support\Facades\Log;
use Exception;

class SAVNotificationService
{
    protected RabbitMQClientService $rabbitMQClient;

    public function __construct()
    {
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Envoie une notification lors de la création d'un ticket
     */
    public function notifyTicketCreated(SupportTicket $ticket): void
    {
        try {
            $this->publishNotification('sav.ticket.created', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->user_id,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'category' => $ticket->category,
                'order_id' => $ticket->order_id,
                'created_at' => $ticket->created_at->toISOString(),
            ]);

            Log::info('Ticket creation notification sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send ticket creation notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification lors de la mise à jour d'un ticket
     */
    public function notifyTicketUpdated(SupportTicket $ticket, array $changes): void
    {
        try {
            $this->publishNotification('sav.ticket.updated', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->user_id,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'assigned_to' => $ticket->assigned_to,
                'changes' => $changes,
                'updated_at' => $ticket->updated_at->toISOString(),
            ]);

            Log::info('Ticket update notification sent', [
                'ticket_id' => $ticket->id,
                'changes' => array_keys($changes),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send ticket update notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification lors de l'assignation d'un ticket
     */
    public function notifyTicketAssigned(SupportTicket $ticket): void
    {
        try {
            $this->publishNotification('sav.ticket.assigned', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->user_id,
                'assigned_to' => $ticket->assigned_to,
                'subject' => $ticket->subject,
                'priority' => $ticket->priority,
                'assigned_at' => now()->toISOString(),
            ]);

            Log::info('Ticket assignment notification sent', [
                'ticket_id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send ticket assignment notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification lors de la résolution d'un ticket
     */
    public function notifyTicketResolved(SupportTicket $ticket): void
    {
        try {
            $this->publishNotification('sav.ticket.resolved', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->user_id,
                'assigned_to' => $ticket->assigned_to,
                'subject' => $ticket->subject,
                'resolved_at' => $ticket->resolved_at->toISOString(),
            ]);

            Log::info('Ticket resolution notification sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send ticket resolution notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification lors de la fermeture d'un ticket
     */
    public function notifyTicketClosed(SupportTicket $ticket): void
    {
        try {
            $this->publishNotification('sav.ticket.closed', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'user_id' => $ticket->user_id,
                'assigned_to' => $ticket->assigned_to,
                'subject' => $ticket->subject,
                'closed_at' => $ticket->closed_at->toISOString(),
            ]);

            Log::info('Ticket closure notification sent', [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send ticket closure notification', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification lors de l'ajout d'un message
     */
    public function notifyMessageAdded(TicketMessage $message, SupportTicket $ticket): void
    {
        try {
            $this->publishNotification('sav.message.added', [
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'sender_id' => $message->sender_id,
                'sender_type' => $message->sender_type,
                'is_internal' => $message->is_internal,
                'user_id' => $ticket->user_id,
                'assigned_to' => $ticket->assigned_to,
                'subject' => $ticket->subject,
                'created_at' => $message->created_at->toISOString(),
            ]);

            Log::info('Message addition notification sent', [
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'sender_type' => $message->sender_type,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send message addition notification', [
                'message_id' => $message->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Envoie une notification d'email au client
     */
    public function sendEmailNotification(string $email, string $subject, string $template, array $data): void
    {
        try {
            $this->rabbitMQClient->publish(
                'microservices_exchange',
                'emails.send',
                [
                    'to' => $email,
                    'subject' => $subject,
                    'template' => $template,
                    'data' => $data,
                    'service' => 'sav-service',
                    'timestamp' => now()->toISOString(),
                ]
            );

            Log::info('Email notification sent', [
                'to' => $email,
                'subject' => $subject,
                'template' => $template,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send email notification', [
                'to' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Récupère les informations utilisateur depuis le service auth
     */
    public function getUserInfo(int $userId): ?array
    {
        try {
            $response = $this->rabbitMQClient->sendRequest(
                'auth-service',
                'GET',
                "/api/users/{$userId}",
                [],
                [],
                10
            );

            if ($response['success'] ?? false) {
                return $response['data'] ?? null;
            }

            Log::warning('Failed to get user info from auth service', [
                'user_id' => $userId,
                'response' => $response,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching user info from auth service', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Récupère les informations de commande depuis le service orders
     */
    public function getOrderInfo(int $orderId): ?array
    {
        try {
            $response = $this->rabbitMQClient->sendRequest(
                'orders-service',
                'GET',
                "/api/orders/{$orderId}",
                [],
                [],
                10
            );

            if ($response['success'] ?? false) {
                return $response['data'] ?? null;
            }

            Log::warning('Failed to get order info from orders service', [
                'order_id' => $orderId,
                'response' => $response,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error fetching order info from orders service', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Publie une notification générique
     */
    protected function publishNotification(string $routingKey, array $data): void
    {
        $this->rabbitMQClient->publish(
            'microservices_exchange',
            $routingKey,
            array_merge($data, [
                'service' => 'sav-service',
                'timestamp' => now()->toISOString(),
            ])
        );
    }
}