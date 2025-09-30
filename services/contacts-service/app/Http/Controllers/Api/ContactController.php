<?php

namespace App\Http\Controllers\Api;

use App\Models\Contact;
use App\Services\ContactNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

class ContactController extends Controller
{
    protected ContactNotificationService $notificationService;

    public function __construct(ContactNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of contacts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query()->with(['activeContactLists']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        if ($request->has('newsletter_subscribed')) {
            $query->where('newsletter_subscribed', $request->boolean('newsletter_subscribed'));
        }

        if ($request->has('marketing_subscribed')) {
            $query->where('marketing_subscribed', $request->boolean('marketing_subscribed'));
        }

        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        if ($request->has('language')) {
            $query->where('language', $request->language);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Tag filter
        if ($request->has('tag')) {
            $query->whereJsonContains('tags', $request->tag);
        }

        // Date filters
        if ($request->has('subscribed_after')) {
            $query->where('subscribed_at', '>=', $request->subscribed_after);
        }

        if ($request->has('subscribed_before')) {
            $query->where('subscribed_at', '<=', $request->subscribed_before);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 15), 100);
        $contacts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contacts->items(),
            'pagination' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    /**
     * Store a newly created contact
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:contacts,email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => 'in:active,inactive,bounced,unsubscribed,complained',
            'source' => 'in:manual,import,api,newsletter_signup,purchase,contact_form',
            'language' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,O',
            'newsletter_subscribed' => 'boolean',
            'marketing_subscribed' => 'boolean',
            'sms_subscribed' => 'boolean',
            'user_id' => 'nullable|integer',
            'custom_fields' => 'nullable|array',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactData = $validator->validated();

        // Set subscription timestamps
        if (!empty($contactData['newsletter_subscribed']) || !empty($contactData['marketing_subscribed'])) {
            $contactData['subscribed_at'] = now();
        }

        $contact = Contact::create($contactData);

        // Send notification
        $this->notificationService->contactCreated($contact->id, $contactData);

        // Send subscription notifications if applicable
        if (!empty($contactData['newsletter_subscribed'])) {
            $this->notificationService->contactSubscribed($contact->id, $contact->email, 'newsletter');
        }

        if (!empty($contactData['marketing_subscribed'])) {
            $this->notificationService->contactSubscribed($contact->id, $contact->email, 'marketing');
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact created successfully',
            'data' => $contact->load(['activeContactLists']),
        ], 201);
    }

    /**
     * Display the specified contact
     */
    public function show(Contact $contact): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $contact->load(['activeContactLists']),
        ]);
    }

    /**
     * Update the specified contact
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:contacts,email,' . $contact->id,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => 'in:active,inactive,bounced,unsubscribed,complained',
            'source' => 'in:manual,import,api,newsletter_signup,purchase,contact_form',
            'language' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:M,F,O',
            'newsletter_subscribed' => 'boolean',
            'marketing_subscribed' => 'boolean',
            'sms_subscribed' => 'boolean',
            'user_id' => 'nullable|integer',
            'custom_fields' => 'nullable|array',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $originalData = $contact->getOriginal();
        $contact->update($validator->validated());

        // Track changes for notifications
        $changes = [];
        foreach ($validator->validated() as $key => $value) {
            if (isset($originalData[$key]) && $originalData[$key] !== $value) {
                $changes[$key] = ['from' => $originalData[$key], 'to' => $value];
            }
        }

        // Send update notification
        if (!empty($changes)) {
            $this->notificationService->contactUpdated($contact->id, $changes);
        }

        // Send subscription/unsubscription notifications
        if (isset($changes['newsletter_subscribed'])) {
            if ($changes['newsletter_subscribed']['to']) {
                $this->notificationService->contactSubscribed($contact->id, $contact->email, 'newsletter');
            } else {
                $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'newsletter');
            }
        }

        if (isset($changes['marketing_subscribed'])) {
            if ($changes['marketing_subscribed']['to']) {
                $this->notificationService->contactSubscribed($contact->id, $contact->email, 'marketing');
            } else {
                $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'marketing');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $contact->load(['activeContactLists']),
        ]);
    }

    /**
     * Remove the specified contact
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully',
        ]);
    }

    /**
     * Subscribe contact to newsletter
     */
    public function subscribe(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:newsletter,marketing,sms',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $validator->validated()['type'];

        switch ($type) {
            case 'newsletter':
                $contact->subscribeToNewsletter();
                break;
            case 'marketing':
                $contact->subscribeToMarketing();
                break;
            case 'sms':
                $contact->update(['sms_subscribed' => true]);
                break;
        }

        $this->notificationService->contactSubscribed($contact->id, $contact->email, $type);

        return response()->json([
            'success' => true,
            'message' => "Contact subscribed to {$type} successfully",
            'data' => $contact->fresh(),
        ]);
    }

    /**
     * Unsubscribe contact
     */
    public function unsubscribe(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'in:newsletter,marketing,sms,all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $validator->validated()['type'] ?? 'all';

        if ($type === 'all' || $type === 'newsletter') {
            $contact->unsubscribeFromNewsletter();
            $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'newsletter');
        }

        if ($type === 'all' || $type === 'marketing') {
            $contact->update(['marketing_subscribed' => false]);
            $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'marketing');
        }

        if ($type === 'all' || $type === 'sms') {
            $contact->update(['sms_subscribed' => false]);
            $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'sms');
        }

        return response()->json([
            'success' => true,
            'message' => "Contact unsubscribed from {$type} successfully",
            'data' => $contact->fresh(),
        ]);
    }

    /**
     * Record email engagement
     */
    public function recordEngagement(Request $request, Contact $contact): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:opened,clicked,bounced',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $engagementType = $validator->validated()['type'];

        switch ($engagementType) {
            case 'opened':
                $contact->recordEmailOpened();
                break;
            case 'clicked':
                $contact->recordEmailClicked();
                break;
            case 'bounced':
                $contact->update(['status' => 'bounced']);
                break;
        }

        $this->notificationService->emailEngagement($contact->id, $contact->email, $engagementType);

        return response()->json([
            'success' => true,
            'message' => "Email {$engagementType} recorded successfully",
            'data' => $contact->fresh(),
        ]);
    }

    /**
     * Bulk operations on contacts
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:delete,subscribe,unsubscribe,add_tag,remove_tag,update_status',
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:contacts,id',
            'data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $action = $validated['action'];
        $contactIds = $validated['contact_ids'];
        $data = $validated['data'] ?? [];

        $contacts = Contact::whereIn('id', $contactIds)->get();
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($contacts as $contact) {
            try {
                switch ($action) {
                    case 'delete':
                        $contact->delete();
                        break;
                        
                    case 'subscribe':
                        $type = $data['type'] ?? 'newsletter';
                        if ($type === 'newsletter') {
                            $contact->subscribeToNewsletter();
                        } elseif ($type === 'marketing') {
                            $contact->subscribeToMarketing();
                        }
                        $this->notificationService->contactSubscribed($contact->id, $contact->email, $type);
                        break;
                        
                    case 'unsubscribe':
                        $contact->unsubscribeFromNewsletter();
                        $this->notificationService->contactUnsubscribed($contact->id, $contact->email, 'newsletter');
                        break;
                        
                    case 'add_tag':
                        if (!empty($data['tag'])) {
                            $contact->addTag($data['tag']);
                        }
                        break;
                        
                    case 'remove_tag':
                        if (!empty($data['tag'])) {
                            $contact->removeTag($data['tag']);
                        }
                        break;
                        
                    case 'update_status':
                        if (!empty($data['status'])) {
                            $contact->update(['status' => $data['status']]);
                        }
                        break;
                }
                
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Contact {$contact->id}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk action completed. {$results['success']} successful, {$results['failed']} failed.",
            'results' => $results,
        ]);
    }
}