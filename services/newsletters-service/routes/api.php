<?php

use App\Http\Controllers\CampaignController;
use App\Http\Controllers\NewsletterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Health check endpoint
Route::get('health', function () {
    return response()->json([
        'status' => 'healthy', 
        'service' => 'newsletters-service',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0'
    ]);
});

// Public newsletter routes (no authentication required)
Route::group(['prefix' => 'newsletters'], function () {
    // Subscribe to newsletter
    Route::post('subscribe', [NewsletterController::class, 'subscribe']);
    
    // Confirm subscription (via email link)
    Route::get('confirm/{token}', [NewsletterController::class, 'confirm']);
    
    // Unsubscribe (via email link)
    Route::get('unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);
    Route::post('unsubscribe/{token}', [NewsletterController::class, 'unsubscribe']);
});

// Protected routes (require authentication)
Route::middleware(['auth:api'])->group(function () {
    
    // Newsletter management routes
    Route::group(['prefix' => 'newsletters'], function () {
        Route::get('/', [NewsletterController::class, 'index']);
        Route::get('stats', [NewsletterController::class, 'stats']);
        Route::post('bulk-import', [NewsletterController::class, 'bulkImport']);
        Route::get('{newsletter}', [NewsletterController::class, 'show']);
        Route::put('{newsletter}', [NewsletterController::class, 'update']);
        Route::delete('{newsletter}', [NewsletterController::class, 'destroy']);
    });

    // Campaign management routes
    Route::group(['prefix' => 'campaigns'], function () {
        Route::get('/', [CampaignController::class, 'index']);
        Route::post('/', [CampaignController::class, 'store']);
        Route::get('{campaign}', [CampaignController::class, 'show']);
        Route::put('{campaign}', [CampaignController::class, 'update']);
        Route::delete('{campaign}', [CampaignController::class, 'destroy']);
        
        // Campaign actions
        Route::post('{campaign}/schedule', [CampaignController::class, 'schedule']);
        Route::post('{campaign}/send', [CampaignController::class, 'send']);
        Route::post('{campaign}/cancel', [CampaignController::class, 'cancel']);
        Route::post('{campaign}/test-send', [CampaignController::class, 'testSend']);
        Route::post('{campaign}/duplicate', [CampaignController::class, 'duplicate']);
        
        // Campaign analytics
        Route::get('{campaign}/stats', [CampaignController::class, 'stats']);
        Route::get('{campaign}/analytics', [CampaignController::class, 'analytics']);
    });
});

// Webhook routes for email service providers (no auth, but should be secured by IP or secret)
Route::group(['prefix' => 'webhooks'], function () {
    // Email delivery webhooks
    Route::post('email-delivered', function (Request $request) {
        // Handle email delivery confirmation
        return response()->json(['status' => 'ok']);
    });
    
    Route::post('email-opened', function (Request $request) {
        // Handle email open tracking
        return response()->json(['status' => 'ok']);
    });
    
    Route::post('email-clicked', function (Request $request) {
        // Handle email click tracking
        return response()->json(['status' => 'ok']);
    });
    
    Route::post('email-bounced', function (Request $request) {
        // Handle email bounce
        return response()->json(['status' => 'ok']);
    });
    
    Route::post('email-complained', function (Request $request) {
        // Handle spam complaints
        return response()->json(['status' => 'ok']);
    });
});

// Admin-only routes
Route::middleware(['auth:api', 'role:admin|newsletter_manager'])->group(function () {
    Route::group(['prefix' => 'admin'], function () {
        // System stats and health
        Route::get('system-stats', function () {
            return response()->json([
                'newsletters' => [
                    'total' => \App\Models\Newsletter::count(),
                    'subscribed' => \App\Models\Newsletter::subscribed()->count(),
                    'unsubscribed' => \App\Models\Newsletter::unsubscribed()->count(),
                    'pending' => \App\Models\Newsletter::pending()->count(),
                    'bounced' => \App\Models\Newsletter::bounced()->count(),
                ],
                'campaigns' => [
                    'total' => \App\Models\Campaign::count(),
                    'draft' => \App\Models\Campaign::draft()->count(),
                    'scheduled' => \App\Models\Campaign::scheduled()->count(),
                    'sent' => \App\Models\Campaign::sent()->count(),
                    'sending' => \App\Models\Campaign::sending()->count(),
                ],
                'database' => [
                    'size' => 'N/A', // Could add database size calculation
                ],
                'queue_status' => [
                    'pending_jobs' => 'N/A', // Could add queue monitoring
                ],
            ]);
        });
        
        // Export data
        Route::get('export/newsletters', function (Request $request) {
            // Export newsletters data (CSV, Excel, etc.)
            return response()->json(['message' => 'Export functionality to be implemented']);
        });
        
        Route::get('export/campaigns', function (Request $request) {
            // Export campaigns data (CSV, Excel, etc.)
            return response()->json(['message' => 'Export functionality to be implemented']);
        });
    });
});