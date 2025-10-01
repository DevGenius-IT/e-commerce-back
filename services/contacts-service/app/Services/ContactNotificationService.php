<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

class ContactNotificationService
{
    private $connection;
    private $channel;
    private $exchange;

    public function __construct()
    {
        $this->exchange = config('app.rabbitmq_exchange', 'microservices');
        $this->initializeConnection();
    }

    private function initializeConnection()
    {
        try {
            $this->connection = new AMQPStreamConnection(
                env('RABBITMQ_HOST', 'localhost'),
                env('RABBITMQ_PORT', 5672),
                env('RABBITMQ_USER', 'guest'),
                env('RABBITMQ_PASSWORD', 'guest'),
                env('RABBITMQ_VHOST', '/')
            );
            $this->channel = $this->connection->channel();
            
            // Declare exchange
            $this->channel->exchange_declare($this->exchange, 'topic', false, true, false);
        } catch (\Exception $e) {
            Log::error('Failed to initialize RabbitMQ connection', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Publish contact event to message broker
     */
    private function publish(string $routingKey, array $data): bool
    {
        try {
            if (!$this->channel) {
                $this->initializeConnection();
            }

            $message = new AMQPMessage(
                json_encode($data),
                ['content_type' => 'application/json', 'delivery_mode' => 2]
            );

            $this->channel->basic_publish($message, $this->exchange, $routingKey);
            
            Log::info('Contact notification published', [
                'routing_key' => $routingKey,
                'contact_id' => $data['contact_id'] ?? null,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish contact notification', [
                'routing_key' => $routingKey,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Notify when a new contact is created
     */
    public function contactCreated(int $contactId, array $contactData): bool
    {
        return $this->publish('contacts.contact.created', [
            'event' => 'contact.created',
            'contact_id' => $contactId,
            'email' => $contactData['email'] ?? null,
            'source' => $contactData['source'] ?? 'manual',
            'subscribed_to_newsletter' => $contactData['newsletter_subscribed'] ?? false,
            'subscribed_to_marketing' => $contactData['marketing_subscribed'] ?? false,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact is updated
     */
    public function contactUpdated(int $contactId, array $changes): bool
    {
        return $this->publish('contacts.contact.updated', [
            'event' => 'contact.updated',
            'contact_id' => $contactId,
            'changes' => $changes,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact subscribes to newsletter
     */
    public function contactSubscribed(int $contactId, string $email, string $type = 'newsletter'): bool
    {
        return $this->publish('contacts.contact.subscribed', [
            'event' => 'contact.subscribed',
            'contact_id' => $contactId,
            'email' => $email,
            'subscription_type' => $type,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact unsubscribes
     */
    public function contactUnsubscribed(int $contactId, string $email, string $type = 'newsletter'): bool
    {
        return $this->publish('contacts.contact.unsubscribed', [
            'event' => 'contact.unsubscribed',
            'contact_id' => $contactId,
            'email' => $email,
            'subscription_type' => $type,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact is added to a list
     */
    public function contactAddedToList(int $contactId, int $listId, string $listName): bool
    {
        return $this->publish('contacts.list.contact_added', [
            'event' => 'contact.added_to_list',
            'contact_id' => $contactId,
            'list_id' => $listId,
            'list_name' => $listName,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact is removed from a list
     */
    public function contactRemovedFromList(int $contactId, int $listId, string $listName): bool
    {
        return $this->publish('contacts.list.contact_removed', [
            'event' => 'contact.removed_from_list',
            'contact_id' => $contactId,
            'list_id' => $listId,
            'list_name' => $listName,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when a new contact list is created
     */
    public function listCreated(int $listId, array $listData): bool
    {
        return $this->publish('contacts.list.created', [
            'event' => 'list.created',
            'list_id' => $listId,
            'name' => $listData['name'] ?? null,
            'type' => $listData['type'] ?? null,
            'is_dynamic' => $listData['is_dynamic'] ?? false,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact list is updated
     */
    public function listUpdated(int $listId, array $changes): bool
    {
        return $this->publish('contacts.list.updated', [
            'event' => 'list.updated',
            'list_id' => $listId,
            'changes' => $changes,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when dynamic list is synced
     */
    public function listSynced(int $listId, int $contactCount): bool
    {
        return $this->publish('contacts.list.synced', [
            'event' => 'list.synced',
            'list_id' => $listId,
            'contact_count' => $contactCount,
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Notify when contact email engagement occurs
     */
    public function emailEngagement(int $contactId, string $email, string $engagementType): bool
    {
        return $this->publish('contacts.engagement.' . $engagementType, [
            'event' => 'email.' . $engagementType,
            'contact_id' => $contactId,
            'email' => $email,
            'engagement_type' => $engagementType, // 'opened', 'clicked', 'bounced'
            'timestamp' => now()->toISOString(),
            'service' => 'contacts-service',
        ]);
    }

    /**
     * Clean up connection
     */
    public function __destruct()
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (\Exception $e) {
            Log::error('Error closing RabbitMQ connection', ['error' => $e->getMessage()]);
        }
    }
}