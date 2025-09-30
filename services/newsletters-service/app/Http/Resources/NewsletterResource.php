<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NewsletterResource extends JsonResource
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
            'email' => $this->email,
            'name' => $this->name,
            'phone' => $this->phone,
            'status' => $this->status,
            'status_display' => $this->status_display,
            'preferences' => $this->preferences,
            'subscription_source' => $this->subscription_source,
            'bounce_count' => $this->bounce_count,
            'subscription_duration' => $this->subscription_duration,
            'subscribed_at' => $this->subscribed_at?->toISOString(),
            'unsubscribed_at' => $this->unsubscribed_at?->toISOString(),
            'last_bounce_at' => $this->last_bounce_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Include unsubscribe URL only for API consumers that need it
            'unsubscribe_url' => $this->when(
                $request->has('include_unsubscribe_url') && $request->include_unsubscribe_url,
                $this->unsubscribe_url
            ),
            
            // Include campaigns if loaded
            'campaigns' => CampaignResource::collection($this->whenLoaded('campaigns')),
            
            // Include campaign statistics if available
            'campaign_stats' => $this->when(
                $this->relationLoaded('campaigns'),
                function () {
                    return [
                        'total_campaigns_received' => $this->campaigns->count(),
                        'total_opened' => $this->campaigns->whereNotNull('pivot.opened_at')->count(),
                        'total_clicked' => $this->campaigns->whereNotNull('pivot.clicked_at')->count(),
                        'last_campaign_opened' => $this->campaigns->whereNotNull('pivot.opened_at')->max('pivot.opened_at'),
                        'last_campaign_clicked' => $this->campaigns->whereNotNull('pivot.clicked_at')->max('pivot.clicked_at'),
                    ];
                }
            ),
            
            // Admin-only fields
            'notes' => $this->when(
                $request->user()?->hasRole(['admin', 'newsletter_manager']),
                $this->notes
            ),
        ];
    }
}