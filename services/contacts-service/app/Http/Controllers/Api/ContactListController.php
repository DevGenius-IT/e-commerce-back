<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactList;
use App\Models\Contact;
use App\Services\ContactNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Validator;

class ContactListController extends Controller
{
    protected ContactNotificationService $notificationService;

    public function __construct(ContactNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Display a listing of contact lists
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactList::query();

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        if ($request->has('is_dynamic')) {
            $query->where('is_dynamic', $request->boolean('is_dynamic'));
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 15), 100);
        $lists = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $lists->items(),
            'pagination' => [
                'current_page' => $lists->currentPage(),
                'last_page' => $lists->lastPage(),
                'per_page' => $lists->perPage(),
                'total' => $lists->total(),
            ],
        ]);
    }

    /**
     * Store a newly created contact list
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:contact_lists,name',
            'description' => 'nullable|string',
            'type' => 'required|in:marketing,newsletter,segmentation,custom',
            'status' => 'in:active,inactive,archived',
            'is_dynamic' => 'boolean',
            'criteria' => 'nullable|array',
            'criteria.*.field' => 'required_with:criteria|string',
            'criteria.*.operator' => 'required_with:criteria|string',
            'criteria.*.value' => 'required_with:criteria',
            'criteria.*.logic' => 'in:and,or',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $listData = $validator->validated();
        $listData['created_by'] = auth()->id();

        $contactList = ContactList::create($listData);

        // If it's a dynamic list, sync contacts immediately
        if ($contactList->is_dynamic && !empty($contactList->criteria)) {
            $contactList->syncDynamicContacts(auth()->id());
        }

        // Send notification
        $this->notificationService->listCreated($contactList->id, $listData);

        return response()->json([
            'success' => true,
            'message' => 'Contact list created successfully',
            'data' => $contactList,
        ], 201);
    }

    /**
     * Display the specified contact list
     */
    public function show(ContactList $contactList): JsonResponse
    {
        $contactList->load(['activeContacts' => function($query) {
            $query->select('contacts.id', 'email', 'first_name', 'last_name', 'status', 'newsletter_subscribed')
                  ->orderBy('contact_list_contacts.added_at', 'desc');
        }]);

        return response()->json([
            'success' => true,
            'data' => $contactList,
            'stats' => $contactList->getStats(),
        ]);
    }

    /**
     * Update the specified contact list
     */
    public function update(Request $request, ContactList $contactList): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:contact_lists,name,' . $contactList->id,
            'description' => 'nullable|string',
            'type' => 'in:marketing,newsletter,segmentation,custom',
            'status' => 'in:active,inactive,archived',
            'is_dynamic' => 'boolean',
            'criteria' => 'nullable|array',
            'criteria.*.field' => 'required_with:criteria|string',
            'criteria.*.operator' => 'required_with:criteria|string',
            'criteria.*.value' => 'required_with:criteria',
            'criteria.*.logic' => 'in:and,or',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $originalData = $contactList->getOriginal();
        $updateData = $validator->validated();
        $updateData['updated_by'] = auth()->id();

        $contactList->update($updateData);

        // Track changes for notifications
        $changes = [];
        foreach ($updateData as $key => $value) {
            if (isset($originalData[$key]) && $originalData[$key] !== $value) {
                $changes[$key] = ['from' => $originalData[$key], 'to' => $value];
            }
        }

        // If criteria changed and it's dynamic, re-sync
        if (isset($changes['criteria']) && $contactList->is_dynamic) {
            $contactList->syncDynamicContacts(auth()->id());
        }

        // Send notification
        if (!empty($changes)) {
            $this->notificationService->listUpdated($contactList->id, $changes);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contact list updated successfully',
            'data' => $contactList->fresh(),
        ]);
    }

    /**
     * Remove the specified contact list
     */
    public function destroy(ContactList $contactList): JsonResponse
    {
        $contactList->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact list deleted successfully',
        ]);
    }

    /**
     * Add contacts to a list
     */
    public function addContacts(Request $request, ContactList $contactList): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:contacts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactIds = $validator->validated()['contact_ids'];
        $contactList->addContacts($contactIds, auth()->id());

        // Send notifications for each contact added
        $contacts = Contact::whereIn('id', $contactIds)->get();
        foreach ($contacts as $contact) {
            $this->notificationService->contactAddedToList(
                $contact->id,
                $contactList->id,
                $contactList->name
            );
        }

        return response()->json([
            'success' => true,
            'message' => count($contactIds) . ' contacts added to list successfully',
            'data' => $contactList->fresh(),
        ]);
    }

    /**
     * Remove contacts from a list
     */
    public function removeContacts(Request $request, ContactList $contactList): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array|min:1',
            'contact_ids.*' => 'integer|exists:contacts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactIds = $validator->validated()['contact_ids'];
        
        foreach ($contactIds as $contactId) {
            $contact = Contact::find($contactId);
            if ($contact) {
                $contactList->removeContact($contact);
                $this->notificationService->contactRemovedFromList(
                    $contact->id,
                    $contactList->id,
                    $contactList->name
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($contactIds) . ' contacts removed from list successfully',
            'data' => $contactList->fresh(),
        ]);
    }

    /**
     * Sync dynamic list (refresh contacts based on criteria)
     */
    public function sync(ContactList $contactList): JsonResponse
    {
        if (!$contactList->is_dynamic) {
            return response()->json([
                'success' => false,
                'message' => 'Only dynamic lists can be synced',
            ], 400);
        }

        $contactList->syncDynamicContacts(auth()->id());
        $contactList->refresh();

        $this->notificationService->listSynced($contactList->id, $contactList->contact_count);

        return response()->json([
            'success' => true,
            'message' => 'Dynamic list synced successfully',
            'data' => $contactList,
            'contact_count' => $contactList->contact_count,
        ]);
    }

    /**
     * Get list statistics
     */
    public function stats(ContactList $contactList): JsonResponse
    {
        $stats = $contactList->getStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Export contacts from a list
     */
    public function export(Request $request, ContactList $contactList): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'format' => 'in:csv,json',
            'fields' => 'array',
            'fields.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $format = $request->get('format', 'csv');
        $fields = $request->get('fields', [
            'email', 'first_name', 'last_name', 'company', 'phone',
            'status', 'newsletter_subscribed', 'marketing_subscribed'
        ]);

        $contacts = $contactList->activeContacts()
                                ->select($fields)
                                ->get()
                                ->toArray();

        if ($format === 'csv') {
            $csvData = [];
            if (!empty($contacts)) {
                $csvData[] = array_keys($contacts[0]); // Headers
                foreach ($contacts as $contact) {
                    $csvData[] = array_values($contact);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $csvData,
                'filename' => 'contacts-' . $contactList->name . '-' . date('Y-m-d') . '.csv',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $contacts,
            'filename' => 'contacts-' . $contactList->name . '-' . date('Y-m-d') . '.json',
        ]);
    }

    /**
     * Duplicate a contact list
     */
    public function duplicate(Request $request, ContactList $contactList): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:contact_lists,name',
            'copy_contacts' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $newName = $validator->validated()['name'];
        $copyContacts = $request->boolean('copy_contacts', true);

        $newList = $contactList->replicate();
        $newList->name = $newName;
        $newList->contact_count = 0;
        $newList->created_by = auth()->id();
        $newList->updated_by = auth()->id();
        $newList->save();

        if ($copyContacts && !$contactList->is_dynamic) {
            $contactIds = $contactList->activeContacts()->pluck('contacts.id')->toArray();
            if (!empty($contactIds)) {
                $newList->addContacts($contactIds, auth()->id());
            }
        } elseif ($newList->is_dynamic) {
            $newList->syncDynamicContacts(auth()->id());
        }

        $this->notificationService->listCreated($newList->id, $newList->toArray());

        return response()->json([
            'success' => true,
            'message' => 'Contact list duplicated successfully',
            'data' => $newList->fresh(),
        ], 201);
    }
}