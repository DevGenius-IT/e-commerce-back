<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Shared\Services\RabbitMQClientService;
use Carbon\Carbon;

class CampaignController extends Controller
{
    protected RabbitMQClientService $rabbitMQClient;

    public function __construct()
    {
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Display a listing of campaigns.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Campaign::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by campaign type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Search by name or subject
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $campaigns = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => CampaignResource::collection($campaigns),
            'meta' => [
                'total' => $campaigns->total(),
                'per_page' => $campaigns->perPage(),
                'current_page' => $campaigns->currentPage(),
                'last_page' => $campaigns->lastPage(),
            ]
        ]);
    }

    /**
     * Store a new campaign.
     */
    public function store(StoreCampaignRequest $request): JsonResponse
    {
        try {
            $campaign = Campaign::create($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign created successfully',
                'data' => new CampaignResource($campaign)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified campaign.
     */
    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new CampaignResource($campaign->load('newsletters'))
        ]);
    }

    /**
     * Update the specified campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        try {
            // Only allow updates if campaign is in draft status
            if (!$campaign->isDraft()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft campaigns can be updated'
                ], 422);
            }

            $campaign->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign updated successfully',
                'data' => new CampaignResource($campaign)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified campaign.
     */
    public function destroy(Campaign $campaign): JsonResponse
    {
        try {
            // Only allow deletion if campaign is draft or cancelled
            if (!in_array($campaign->status, ['draft', 'cancelled'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft or cancelled campaigns can be deleted'
                ], 422);
            }

            $campaign->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule a campaign.
     */
    public function schedule(Request $request, Campaign $campaign): JsonResponse
    {
        $request->validate([
            'scheduled_at' => 'required|date|after:now',
        ]);

        try {
            if (!$campaign->isDraft()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft campaigns can be scheduled'
                ], 422);
            }

            $scheduledAt = Carbon::parse($request->scheduled_at);
            $campaign->schedule($scheduledAt);

            // Calculate recipients count
            $recipientsCount = $this->calculateRecipientsCount($campaign);
            $campaign->update(['total_recipients' => $recipientsCount]);

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign scheduled successfully',
                'data' => new CampaignResource($campaign)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to schedule campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a campaign immediately.
     */
    public function send(Campaign $campaign): JsonResponse
    {
        try {
            if (!in_array($campaign->status, ['draft', 'scheduled'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only draft or scheduled campaigns can be sent'
                ], 422);
            }

            // Calculate recipients count if not set
            if ($campaign->total_recipients == 0) {
                $recipientsCount = $this->calculateRecipientsCount($campaign);
                $campaign->update(['total_recipients' => $recipientsCount]);
            }

            $campaign->markAsSending();

            // Send campaign via RabbitMQ
            $this->sendCampaignViaQueue($campaign);

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign is being sent',
                'data' => new CampaignResource($campaign)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a scheduled campaign.
     */
    public function cancel(Campaign $campaign): JsonResponse
    {
        try {
            if (!$campaign->isScheduled()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only scheduled campaigns can be cancelled'
                ], 422);
            }

            $campaign->cancel();

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign cancelled successfully',
                'data' => new CampaignResource($campaign)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics.
     */
    public function stats(Campaign $campaign): JsonResponse
    {
        try {
            // Update statistics before returning
            $campaign->updateStatistics();

            $stats = $campaign->getPerformanceMetrics();

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get campaign statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test send campaign to specific email.
     */
    public function testSend(Request $request, Campaign $campaign): JsonResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        try {
            // Send test email via RabbitMQ
            $this->rabbitMQClient->publishMessage('email.send', [
                'type' => 'campaign_test',
                'recipient' => $request->test_email,
                'campaign_id' => $campaign->id,
                'data' => [
                    'subject' => '[TEST] ' . $campaign->subject,
                    'html_content' => $campaign->content,
                    'plain_content' => $campaign->plain_text,
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test email',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate a campaign.
     */
    public function duplicate(Campaign $campaign): JsonResponse
    {
        try {
            $duplicate = $campaign->replicate();
            $duplicate->name = $campaign->name . ' (Copy)';
            $duplicate->status = 'draft';
            $duplicate->scheduled_at = null;
            $duplicate->sent_at = null;
            $duplicate->total_recipients = 0;
            $duplicate->total_sent = 0;
            $duplicate->total_delivered = 0;
            $duplicate->total_opened = 0;
            $duplicate->total_clicked = 0;
            $duplicate->total_bounced = 0;
            $duplicate->total_unsubscribed = 0;
            $duplicate->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Campaign duplicated successfully',
                'data' => new CampaignResource($duplicate)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to duplicate campaign',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign analytics.
     */
    public function analytics(Campaign $campaign): JsonResponse
    {
        try {
            $analytics = [
                'performance' => $campaign->getPerformanceMetrics(),
                'timeline' => [
                    'created_at' => $campaign->created_at,
                    'scheduled_at' => $campaign->scheduled_at,
                    'sent_at' => $campaign->sent_at,
                ],
                'engagement_over_time' => $this->getEngagementOverTime($campaign),
                'top_clicked_links' => $this->getTopClickedLinks($campaign),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get campaign analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate recipients count based on targeting criteria.
     */
    private function calculateRecipientsCount(Campaign $campaign): int
    {
        $query = Newsletter::subscribed();
        
        // Apply targeting criteria if any
        if ($campaign->targeting_criteria) {
            // Add logic here to apply targeting criteria
            // For now, we'll just count all subscribed newsletters
        }
        
        return $query->count();
    }

    /**
     * Send campaign via RabbitMQ queue.
     */
    private function sendCampaignViaQueue(Campaign $campaign): void
    {
        $this->rabbitMQClient->publishMessage('campaign.send', [
            'campaign_id' => $campaign->id,
            'scheduled_at' => now()->toISOString(),
        ]);
    }

    /**
     * Get engagement over time data.
     */
    private function getEngagementOverTime(Campaign $campaign): array
    {
        // This is a simplified version - in real implementation,
        // you'd query the newsletter_campaigns pivot table for detailed analytics
        return [
            'opens_by_hour' => [],
            'clicks_by_hour' => [],
            'peak_engagement_time' => null,
        ];
    }

    /**
     * Get top clicked links data.
     */
    private function getTopClickedLinks(Campaign $campaign): array
    {
        // This would analyze click_data JSON from newsletter_campaigns table
        return [];
    }
}