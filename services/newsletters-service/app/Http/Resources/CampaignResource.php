<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subject' => $this->subject,
            'status' => $this->status,
            'campaign_type' => $this->campaign_type,
            'total_recipients' => $this->total_recipients,
            'total_sent' => $this->total_sent,
            'total_delivered' => $this->total_delivered,
            'total_opened' => $this->total_opened,
            'total_clicked' => $this->total_clicked,
            'total_bounced' => $this->total_bounced,
            'total_unsubscribed' => $this->total_unsubscribed,
            
            // Performance metrics
            'performance_metrics' => [
                'open_rate' => $this->open_rate,
                'click_rate' => $this->click_rate,
                'bounce_rate' => $this->bounce_rate,
                'delivery_rate' => $this->delivery_rate,
                'unsubscribe_rate' => $this->unsubscribe_rate,
            ],
            
            // Timestamps
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Content - only include if requested
            'content' => $this->when(
                $request->has('include_content') && $request->include_content,
                $this->content
            ),
            
            'plain_text' => $this->when(
                $request->has('include_content') && $request->include_content,
                $this->plain_text
            ),
            
            // Targeting criteria - admin only
            'targeting_criteria' => $this->when(
                $request->user()?->hasRole(['admin', 'newsletter_manager']),
                $this->targeting_criteria
            ),
            
            // Notes - admin only
            'notes' => $this->when(
                $request->user()?->hasRole(['admin', 'newsletter_manager']),
                $this->notes
            ),
            
            // Include newsletters if loaded
            'newsletters' => NewsletterResource::collection($this->whenLoaded('newsletters')),
            
            // Created by user info if available
            'created_by' => $this->when(
                $this->created_by,
                function () {
                    return [
                        'id' => $this->created_by,
                        // You could load user details here if needed
                    ];
                }
            ),
            
            // Status helpers
            'status_info' => [
                'is_draft' => $this->isDraft(),
                'is_scheduled' => $this->isScheduled(),
                'is_sending' => $this->isSending(),
                'is_sent' => $this->isSent(),
                'is_cancelled' => $this->isCancelled(),
                'is_failed' => $this->isFailed(),
            ],
        ];
    }
}