<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Http\Requests\UpdateNewsletterRequest;
use App\Http\Resources\NewsletterResource;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Shared\Services\RabbitMQClientService;

class NewsletterController extends Controller
{
    protected RabbitMQClientService $rabbitMQClient;

    public function __construct()
    {
        $this->rabbitMQClient = new RabbitMQClientService();
    }

    /**
     * Display a listing of newsletters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Newsletter::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by subscription source
        if ($request->has('source')) {
            $query->bySource($request->source);
        }

        // Search by email or name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        $newsletters = $query->orderBy('created_at', 'desc')
                            ->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => NewsletterResource::collection($newsletters),
            'meta' => [
                'total' => $newsletters->total(),
                'per_page' => $newsletters->perPage(),
                'current_page' => $newsletters->currentPage(),
                'last_page' => $newsletters->lastPage(),
            ]
        ]);
    }

    /**
     * Subscribe to newsletter.
     */
    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        try {
            // Check if email already exists
            $existingNewsletter = Newsletter::where('email', $request->email)->first();

            if ($existingNewsletter) {
                if ($existingNewsletter->isSubscribed()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Email is already subscribed to newsletter'
                    ], 422);
                } else {
                    // Resubscribe
                    $existingNewsletter->subscribe();
                    $newsletter = $existingNewsletter;
                }
            } else {
                // Create new subscription
                $newsletter = Newsletter::create([
                    'email' => $request->email,
                    'name' => $request->name,
                    'phone' => $request->phone,
                    'status' => 'pending', // Will be confirmed via email
                    'subscription_source' => $request->source ?? 'api',
                    'preferences' => $request->preferences ?? [],
                ]);
            }

            // Send confirmation email via RabbitMQ
            $this->sendConfirmationEmail($newsletter);

            return response()->json([
                'status' => 'success',
                'message' => 'Newsletter subscription initiated. Please check your email to confirm.',
                'data' => new NewsletterResource($newsletter)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to subscribe to newsletter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Confirm newsletter subscription.
     */
    public function confirm(string $token): JsonResponse
    {
        try {
            $newsletter = Newsletter::where('unsubscribe_token', $token)->first();

            if (!$newsletter) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid confirmation token'
                ], 404);
            }

            if ($newsletter->isSubscribed()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Email is already confirmed and subscribed'
                ]);
            }

            $newsletter->subscribe();

            // Send welcome email via RabbitMQ
            $this->sendWelcomeEmail($newsletter);

            return response()->json([
                'status' => 'success',
                'message' => 'Newsletter subscription confirmed successfully',
                'data' => new NewsletterResource($newsletter)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to confirm subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unsubscribe from newsletter.
     */
    public function unsubscribe(string $token): JsonResponse
    {
        try {
            $newsletter = Newsletter::where('unsubscribe_token', $token)->first();

            if (!$newsletter) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid unsubscribe token'
                ], 404);
            }

            if ($newsletter->isUnsubscribed()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Email is already unsubscribed'
                ]);
            }

            $newsletter->unsubscribe();

            // Send unsubscribe confirmation via RabbitMQ
            $this->sendUnsubscribeConfirmation($newsletter);

            return response()->json([
                'status' => 'success',
                'message' => 'Successfully unsubscribed from newsletter',
                'data' => new NewsletterResource($newsletter)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unsubscribe',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show newsletter details.
     */
    public function show(Newsletter $newsletter): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new NewsletterResource($newsletter->load('campaigns'))
        ]);
    }

    /**
     * Update newsletter preferences.
     */
    public function update(UpdateNewsletterRequest $request, Newsletter $newsletter): JsonResponse
    {
        try {
            $newsletter->update($request->validated());

            if ($request->has('preferences')) {
                $newsletter->updatePreferences($request->preferences);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Newsletter updated successfully',
                'data' => new NewsletterResource($newsletter)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update newsletter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove newsletter.
     */
    public function destroy(Newsletter $newsletter): JsonResponse
    {
        try {
            $newsletter->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Newsletter deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete newsletter',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get newsletter statistics.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_newsletters' => Newsletter::count(),
                'subscribed' => Newsletter::subscribed()->count(),
                'unsubscribed' => Newsletter::unsubscribed()->count(),
                'pending' => Newsletter::pending()->count(),
                'bounced' => Newsletter::bounced()->count(),
                'recent_subscriptions' => Newsletter::recentlySubscribed(7)->count(),
                'monthly_growth' => [
                    'this_month' => Newsletter::where('created_at', '>=', now()->startOfMonth())->count(),
                    'last_month' => Newsletter::where('created_at', '>=', now()->subMonth()->startOfMonth())
                                             ->where('created_at', '<', now()->startOfMonth())->count(),
                ],
                'subscription_sources' => Newsletter::selectRaw('subscription_source, COUNT(*) as count')
                                                   ->groupBy('subscription_source')
                                                   ->get(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get newsletter statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk import newsletters.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'newsletters' => 'required|array|min:1|max:1000',
            'newsletters.*.email' => 'required|email|distinct',
            'newsletters.*.name' => 'nullable|string|max:255',
            'newsletters.*.subscription_source' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($request->newsletters as $index => $newsletterData) {
                try {
                    $existing = Newsletter::where('email', $newsletterData['email'])->first();
                    
                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    Newsletter::create([
                        'email' => $newsletterData['email'],
                        'name' => $newsletterData['name'] ?? null,
                        'status' => 'subscribed', // Bulk imports are pre-confirmed
                        'subscribed_at' => now(),
                        'subscription_source' => $newsletterData['subscription_source'] ?? 'bulk_import',
                    ]);

                    $imported++;

                } catch (\Exception $e) {
                    $errors[] = "Row {$index}: " . $e->getMessage();
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk import completed',
                'data' => [
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'errors' => $errors,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send confirmation email via RabbitMQ.
     */
    private function sendConfirmationEmail(Newsletter $newsletter): void
    {
        // In local development, skip RabbitMQ to avoid connection issues
        if (app()->environment('local')) {
            Log::info('Skipping email send in local environment', [
                'newsletter_id' => $newsletter->id,
                'email' => $newsletter->email
            ]);
            return;
        }

        $this->rabbitMQClient->publish('email', 'send', [
            'type' => 'newsletter_confirmation',
            'recipient' => $newsletter->email,
            'data' => [
                'name' => $newsletter->name,
                'confirmation_url' => config('app.url') . '/api/newsletters/confirm/' . $newsletter->unsubscribe_token,
            ]
        ]);
    }

    /**
     * Send welcome email via RabbitMQ.
     */
    private function sendWelcomeEmail(Newsletter $newsletter): void
    {
        $this->rabbitMQClient->publish('email', 'send', [
            'type' => 'newsletter_welcome',
            'recipient' => $newsletter->email,
            'data' => [
                'name' => $newsletter->name,
                'unsubscribe_url' => $newsletter->unsubscribe_url,
            ]
        ]);
    }

    /**
     * Send unsubscribe confirmation via RabbitMQ.
     */
    private function sendUnsubscribeConfirmation(Newsletter $newsletter): void
    {
        $this->rabbitMQClient->publish('email', 'send', [
            'type' => 'newsletter_unsubscribe_confirmation',
            'recipient' => $newsletter->email,
            'data' => [
                'name' => $newsletter->name,
            ]
        ]);
    }
}