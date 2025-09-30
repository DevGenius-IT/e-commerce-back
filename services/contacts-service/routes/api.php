<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactListController;
use App\Http\Controllers\Api\ContactTagController;

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

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'contacts-service',
        'timestamp' => now(),
    ]);
});

// Basic status check (compatible with make health)
Route::get('/status', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'contacts-service',
        'database' => 'connected',
        'timestamp' => now(),
    ]);
});

// Protected routes (require JWT authentication)
Route::middleware(['auth:api'])->group(function () {
    
    // Contacts Routes
    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::post('/', [ContactController::class, 'store']);
        Route::get('/{contact}', [ContactController::class, 'show']);
        Route::put('/{contact}', [ContactController::class, 'update']);
        Route::delete('/{contact}', [ContactController::class, 'destroy']);
        
        // Subscription management
        Route::post('/{contact}/subscribe', [ContactController::class, 'subscribe']);
        Route::post('/{contact}/unsubscribe', [ContactController::class, 'unsubscribe']);
        
        // Email engagement tracking
        Route::post('/{contact}/engagement', [ContactController::class, 'recordEngagement']);
        
        // Bulk operations
        Route::post('/bulk-action', [ContactController::class, 'bulkAction']);
    });

    // Contact Lists Routes
    Route::prefix('lists')->group(function () {
        Route::get('/', [ContactListController::class, 'index']);
        Route::post('/', [ContactListController::class, 'store']);
        Route::get('/{contactList}', [ContactListController::class, 'show']);
        Route::put('/{contactList}', [ContactListController::class, 'update']);
        Route::delete('/{contactList}', [ContactListController::class, 'destroy']);
        
        // List management
        Route::post('/{contactList}/contacts', [ContactListController::class, 'addContacts']);
        Route::delete('/{contactList}/contacts', [ContactListController::class, 'removeContacts']);
        Route::post('/{contactList}/sync', [ContactListController::class, 'sync']);
        Route::post('/{contactList}/duplicate', [ContactListController::class, 'duplicate']);
        
        // Statistics and export
        Route::get('/{contactList}/stats', [ContactListController::class, 'stats']);
        Route::get('/{contactList}/export', [ContactListController::class, 'export']);
    });

    // Contact Tags Routes
    Route::prefix('tags')->group(function () {
        Route::get('/', [ContactTagController::class, 'index']);
        Route::post('/', [ContactTagController::class, 'store']);
        Route::get('/popular', [ContactTagController::class, 'popular']);
        Route::get('/{contactTag}', [ContactTagController::class, 'show']);
        Route::put('/{contactTag}', [ContactTagController::class, 'update']);
        Route::delete('/{contactTag}', [ContactTagController::class, 'destroy']);
        
        // Tag management
        Route::get('/{contactTag}/contacts', [ContactTagController::class, 'contacts']);
        Route::post('/{contactTag}/apply', [ContactTagController::class, 'applyToContacts']);
        Route::delete('/{contactTag}/remove', [ContactTagController::class, 'removeFromContacts']);
        Route::post('/{contactTag}/merge', [ContactTagController::class, 'merge']);
        Route::get('/{contactTag}/stats', [ContactTagController::class, 'stats']);
    });

});

// Public routes (no authentication required)
Route::prefix('public')->group(function () {
    
    // Public contact subscription (for newsletter signup forms)
    Route::post('subscribe', function (Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'source' => 'nullable|string',
            'language' => 'nullable|string|size:2',
            'newsletter' => 'boolean',
            'marketing' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['source'] = $data['source'] ?? 'newsletter_signup';
        $data['newsletter_subscribed'] = $data['newsletter'] ?? true;
        $data['marketing_subscribed'] = $data['marketing'] ?? false;
        $data['status'] = 'active';
        
        // Remove non-model fields
        unset($data['newsletter'], $data['marketing']);

        try {
            $contact = \App\Models\Contact::where('email', $data['email'])->first();
            
            if ($contact) {
                // Update existing contact
                $contact->update($data);
                $message = 'Subscription updated successfully';
            } else {
                // Create new contact
                $data['subscribed_at'] = now();
                $contact = \App\Models\Contact::create($data);
                $message = 'Subscription successful';
            }

            // Send notification
            $notificationService = new \App\Services\ContactNotificationService();
            if ($data['newsletter_subscribed']) {
                $notificationService->contactSubscribed($contact->id, $contact->email, 'newsletter');
            }
            if ($data['marketing_subscribed']) {
                $notificationService->contactSubscribed($contact->id, $contact->email, 'marketing');
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'newsletter_subscribed' => $contact->newsletter_subscribed,
                    'marketing_subscribed' => $contact->marketing_subscribed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    });

    // Public unsubscribe (for email unsubscribe links)
    Route::post('unsubscribe', function (Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'email' => 'required|email|exists:contacts,email',
            'type' => 'in:newsletter,marketing,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $validator->validated()['email'];
        $type = $validator->validated()['type'] ?? 'newsletter';

        $contact = \App\Models\Contact::where('email', $email)->first();

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
            ], 404);
        }

        // Unsubscribe based on type
        if ($type === 'all' || $type === 'newsletter') {
            $contact->unsubscribeFromNewsletter();
        }

        if ($type === 'all' || $type === 'marketing') {
            $contact->update(['marketing_subscribed' => false]);
        }

        // Send notification
        $notificationService = new \App\Services\ContactNotificationService();
        $notificationService->contactUnsubscribed($contact->id, $contact->email, $type);

        return response()->json([
            'success' => true,
            'message' => 'Unsubscribed successfully',
            'data' => [
                'id' => $contact->id,
                'email' => $contact->email,
                'newsletter_subscribed' => $contact->newsletter_subscribed,
                'marketing_subscribed' => $contact->marketing_subscribed,
            ],
        ]);
    });

    // Public email engagement tracking (for email tracking pixels)
    Route::post('track/{contact}/opened', function (\App\Models\Contact $contact) {
        $contact->recordEmailOpened();
        
        $notificationService = new \App\Services\ContactNotificationService();
        $notificationService->emailEngagement($contact->id, $contact->email, 'opened');

        return response()->json(['success' => true]);
    });

    Route::post('track/{contact}/clicked', function (\App\Models\Contact $contact) {
        $contact->recordEmailClicked();
        
        $notificationService = new \App\Services\ContactNotificationService();
        $notificationService->emailEngagement($contact->id, $contact->email, 'clicked');

        return response()->json(['success' => true]);
    });
});

// Get authenticated user info
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});