<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\Newsletter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shared\Services\RabbitMQClientService;

class SendCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Campaign $campaign;
    protected RabbitMQClientService $rabbitMQClient;

    /**
     * Create a new job instance.
     */
    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting campaign send", ['campaign_id' => $this->campaign->id]);

            // Get all subscribed newsletters based on targeting criteria
            $newsletters = $this->getTargetedNewsletters();
            
            if ($newsletters->isEmpty()) {
                Log::warning("No recipients found for campaign", ['campaign_id' => $this->campaign->id]);
                $this->campaign->markAsFailed();
                return;
            }

            // Update campaign with actual recipient count
            $this->campaign->update(['total_recipients' => $newsletters->count()]);

            // Process newsletters in batches
            $batchSize = config('newsletters.batch_size', 100);
            $newsletters->chunk($batchSize, function ($batch) {
                $this->processBatch($batch);
            });

            // Mark campaign as sent
            $this->campaign->markAsSent();
            $this->campaign->updateStatistics();

            Log::info("Campaign sent successfully", [
                'campaign_id' => $this->campaign->id,
                'recipients' => $this->campaign->total_recipients
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to send campaign", [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->campaign->markAsFailed();
            throw $e;
        }
    }

    /**
     * Get newsletters based on targeting criteria.
     */
    protected function getTargetedNewsletters()
    {
        $query = Newsletter::subscribed();
        
        // Apply targeting criteria if specified
        if ($this->campaign->targeting_criteria) {
            $criteria = $this->campaign->targeting_criteria;
            
            // Example targeting criteria applications
            if (isset($criteria['subscription_source'])) {
                $query->whereIn('subscription_source', (array) $criteria['subscription_source']);
            }
            
            if (isset($criteria['subscribed_after'])) {
                $query->where('subscribed_at', '>=', $criteria['subscribed_after']);
            }
            
            if (isset($criteria['subscribed_before'])) {
                $query->where('subscribed_at', '<=', $criteria['subscribed_before']);
            }
            
            if (isset($criteria['exclude_bounced']) && $criteria['exclude_bounced']) {
                $query->where('status', '!=', 'bounced');
            }
            
            if (isset($criteria['max_bounce_count'])) {
                $query->where('bounce_count', '<=', $criteria['max_bounce_count']);
            }
        }
        
        return $query->get();
    }

    /**
     * Process a batch of newsletters.
     */
    protected function processBatch($newsletters): void
    {
        DB::transaction(function () use ($newsletters) {
            foreach ($newsletters as $newsletter) {
                try {
                    // Create newsletter-campaign relationship
                    $this->campaign->newsletters()->attach($newsletter->id, [
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Send email via RabbitMQ
                    $this->sendEmailViaQueue($newsletter);

                } catch (\Exception $e) {
                    Log::error("Failed to process newsletter in campaign", [
                        'campaign_id' => $this->campaign->id,
                        'newsletter_id' => $newsletter->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    // Mark as failed in pivot table
                    $this->campaign->newsletters()->updateExistingPivot($newsletter->id, [
                        'status' => 'failed',
                        'failed_at' => now(),
                        'failure_reason' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    /**
     * Send individual email via RabbitMQ.
     */
    protected function sendEmailViaQueue(Newsletter $newsletter): void
    {
        $emailData = [
            'type' => 'campaign',
            'campaign_id' => $this->campaign->id,
            'newsletter_id' => $newsletter->id,
            'recipient' => [
                'email' => $newsletter->email,
                'name' => $newsletter->name,
            ],
            'subject' => $this->renderSubject($newsletter),
            'html_content' => $this->renderHtmlContent($newsletter),
            'plain_content' => $this->renderPlainContent($newsletter),
            'tracking' => [
                'unsubscribe_url' => $newsletter->unsubscribe_url,
                'campaign_id' => $this->campaign->id,
                'newsletter_id' => $newsletter->id,
            ],
        ];

        $this->rabbitMQClient->publish('email', 'send', $emailData);
    }

    /**
     * Render email subject with personalization.
     */
    protected function renderSubject(Newsletter $newsletter): string
    {
        $subject = $this->campaign->subject;
        
        // Replace common placeholders
        $replacements = [
            '{{name}}' => $newsletter->name ?? '',
            '{{email}}' => $newsletter->email,
            '{{first_name}}' => $this->getFirstName($newsletter->name),
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $subject);
    }

    /**
     * Render HTML content with personalization.
     */
    protected function renderHtmlContent(Newsletter $newsletter): string
    {
        $content = $this->campaign->content;
        
        // Replace common placeholders
        $replacements = [
            '{{name}}' => $newsletter->name ?? 'Subscriber',
            '{{email}}' => $newsletter->email,
            '{{first_name}}' => $this->getFirstName($newsletter->name),
            '{{unsubscribe_url}}' => $newsletter->unsubscribe_url,
            '{{campaign_name}}' => $this->campaign->name,
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Render plain text content with personalization.
     */
    protected function renderPlainContent(Newsletter $newsletter): string
    {
        $content = $this->campaign->plain_text ?? strip_tags($this->campaign->content);
        
        // Replace common placeholders
        $replacements = [
            '{{name}}' => $newsletter->name ?? 'Subscriber',
            '{{email}}' => $newsletter->email,
            '{{first_name}}' => $this->getFirstName($newsletter->name),
            '{{unsubscribe_url}}' => $newsletter->unsubscribe_url,
            '{{campaign_name}}' => $this->campaign->name,
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Extract first name from full name.
     */
    protected function getFirstName(?string $fullName): string
    {
        if (!$fullName) {
            return 'Subscriber';
        }
        
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? 'Subscriber';
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendCampaignJob failed", [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        $this->campaign->markAsFailed();
    }
}