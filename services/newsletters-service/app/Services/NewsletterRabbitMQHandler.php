<?php

namespace App\Services;

use App\Jobs\SendCampaignJob;
use App\Models\Campaign;
use App\Models\Newsletter;
use Illuminate\Support\Facades\Log;
use Shared\Services\RabbitMQRequestHandlerService;

class NewsletterRabbitMQHandler extends RabbitMQRequestHandlerService
{
    /**
     * Handle incoming RabbitMQ messages.
     */
    public function handleMessage(string $routingKey, array $data): array
    {
        try {
            Log::info("Processing RabbitMQ message", ['routing_key' => $routingKey, 'data' => $data]);

            return match ($routingKey) {
                'campaign.send' => $this->handleCampaignSend($data),
                'newsletter.auto_subscribe' => $this->handleAutoSubscribe($data),
                'email.delivery_status' => $this->handleEmailDeliveryStatus($data),
                'email.engagement' => $this->handleEmailEngagement($data),
                default => $this->handleUnknownMessage($routingKey, $data)
            };

        } catch (\Exception $e) {
            Log::error("Failed to process RabbitMQ message", [
                'routing_key' => $routingKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'status' => 'error',
                'message' => 'Failed to process message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle campaign send request.
     */
    protected function handleCampaignSend(array $data): array
    {
        $campaignId = $data['campaign_id'] ?? null;
        
        if (!$campaignId) {
            return ['status' => 'error', 'message' => 'Campaign ID is required'];
        }

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return ['status' => 'error', 'message' => 'Campaign not found'];
        }

        if (!in_array($campaign->status, ['scheduled', 'draft'])) {
            return ['status' => 'error', 'message' => 'Campaign cannot be sent in current status'];
        }

        // Dispatch the campaign sending job
        SendCampaignJob::dispatch($campaign);

        return [
            'status' => 'success',
            'message' => 'Campaign send job dispatched',
            'campaign_id' => $campaignId
        ];
    }

    /**
     * Handle auto-subscribe request (e.g., from user registration).
     */
    protected function handleAutoSubscribe(array $data): array
    {
        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $source = $data['source'] ?? 'auto_subscription';

        if (!$email) {
            return ['status' => 'error', 'message' => 'Email is required'];
        }

        // Check if already subscribed
        $existing = Newsletter::where('email', $email)->first();
        
        if ($existing) {
            if ($existing->isSubscribed()) {
                return [
                    'status' => 'success',
                    'message' => 'Already subscribed',
                    'newsletter_id' => $existing->id
                ];
            } else {
                // Resubscribe
                $existing->subscribe();
                $newsletter = $existing;
            }
        } else {
            // Create new subscription
            $newsletter = Newsletter::create([
                'email' => $email,
                'name' => $name,
                'status' => 'subscribed', // Auto-subscriptions are pre-confirmed
                'subscribed_at' => now(),
                'subscription_source' => $source,
            ]);
        }

        return [
            'status' => 'success',
            'message' => 'Newsletter subscription created/updated',
            'newsletter_id' => $newsletter->id
        ];
    }

    /**
     * Handle email delivery status updates.
     */
    protected function handleEmailDeliveryStatus(array $data): array
    {
        $campaignId = $data['campaign_id'] ?? null;
        $newsletterId = $data['newsletter_id'] ?? null;
        $status = $data['status'] ?? null;
        $timestamp = $data['timestamp'] ?? now();
        $reason = $data['reason'] ?? null;

        if (!$campaignId || !$newsletterId || !$status) {
            return ['status' => 'error', 'message' => 'Missing required fields'];
        }

        $campaign = Campaign::find($campaignId);
        $newsletter = Newsletter::find($newsletterId);

        if (!$campaign || !$newsletter) {
            return ['status' => 'error', 'message' => 'Campaign or newsletter not found'];
        }

        // Update pivot table based on status
        $pivotData = ['updated_at' => now()];

        switch ($status) {
            case 'delivered':
                $pivotData['status'] = 'delivered';
                $pivotData['delivered_at'] = $timestamp;
                break;
            case 'bounced':
                $pivotData['status'] = 'bounced';
                $pivotData['bounced_at'] = $timestamp;
                $pivotData['bounce_reason'] = $reason;
                
                // Update newsletter bounce count
                $newsletter->markAsBounced($reason);
                break;
            case 'failed':
                $pivotData['status'] = 'failed';
                $pivotData['failed_at'] = $timestamp;
                $pivotData['failure_reason'] = $reason;
                break;
        }

        $campaign->newsletters()->updateExistingPivot($newsletterId, $pivotData);
        
        // Update campaign statistics
        $campaign->updateStatistics();

        return [
            'status' => 'success',
            'message' => 'Delivery status updated',
            'campaign_id' => $campaignId,
            'newsletter_id' => $newsletterId
        ];
    }

    /**
     * Handle email engagement events (opens, clicks).
     */
    protected function handleEmailEngagement(array $data): array
    {
        $campaignId = $data['campaign_id'] ?? null;
        $newsletterId = $data['newsletter_id'] ?? null;
        $eventType = $data['event_type'] ?? null; // 'open' or 'click'
        $timestamp = $data['timestamp'] ?? now();
        $userAgent = $data['user_agent'] ?? null;
        $ipAddress = $data['ip_address'] ?? null;
        $clickData = $data['click_data'] ?? null; // For click events

        if (!$campaignId || !$newsletterId || !$eventType) {
            return ['status' => 'error', 'message' => 'Missing required fields'];
        }

        $campaign = Campaign::find($campaignId);
        if (!$campaign) {
            return ['status' => 'error', 'message' => 'Campaign not found'];
        }

        $pivotData = [
            'updated_at' => now(),
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress,
        ];

        if ($eventType === 'open') {
            $pivotData['opened_at'] = $timestamp;
            $pivotData['status'] = 'opened';
            
            // Increment open count
            $currentPivot = $campaign->newsletters()->where('newsletter_id', $newsletterId)->first();
            if ($currentPivot) {
                $pivotData['open_count'] = ($currentPivot->pivot->open_count ?? 0) + 1;
            }
        } elseif ($eventType === 'click') {
            $pivotData['clicked_at'] = $timestamp;
            $pivotData['status'] = 'clicked';
            
            if ($clickData) {
                $pivotData['click_data'] = $clickData;
            }
            
            // Increment click count
            $currentPivot = $campaign->newsletters()->where('newsletter_id', $newsletterId)->first();
            if ($currentPivot) {
                $pivotData['click_count'] = ($currentPivot->pivot->click_count ?? 0) + 1;
            }
        }

        $campaign->newsletters()->updateExistingPivot($newsletterId, $pivotData);
        
        // Update campaign statistics
        $campaign->updateStatistics();

        return [
            'status' => 'success',
            'message' => 'Engagement event recorded',
            'campaign_id' => $campaignId,
            'newsletter_id' => $newsletterId,
            'event_type' => $eventType
        ];
    }

    /**
     * Handle unknown message types.
     */
    protected function handleUnknownMessage(string $routingKey, array $data): array
    {
        Log::warning("Unknown RabbitMQ message type", [
            'routing_key' => $routingKey,
            'data' => $data
        ]);

        return [
            'status' => 'error',
            'message' => 'Unknown message type: ' . $routingKey
        ];
    }

    /**
     * Get the queue name for this handler.
     */
    public function getQueueName(): string
    {
        return 'newsletters_service_queue';
    }

    /**
     * Get the routing keys this handler processes.
     */
    public function getRoutingKeys(): array
    {
        return [
            'campaign.send',
            'newsletter.auto_subscribe',
            'email.delivery_status',
            'email.engagement',
        ];
    }
}