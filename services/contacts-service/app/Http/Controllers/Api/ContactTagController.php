<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactTag;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shared\Components\Controller;
use Illuminate\Support\Facades\Validator;

class ContactTagController extends Controller
{
    /**
     * Display a listing of contact tags
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContactTag::query();

        // Filters
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'usage_count');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->get('per_page', 50), 100);
        $tags = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $tags->items(),
            'pagination' => [
                'current_page' => $tags->currentPage(),
                'last_page' => $tags->lastPage(),
                'per_page' => $tags->perPage(),
                'total' => $tags->total(),
            ],
        ]);
    }

    /**
     * Store a newly created contact tag
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:contact_tags,name',
            'color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'nullable|string',
            'type' => 'in:system,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tagData = $validator->validated();
        $tagData['type'] = $tagData['type'] ?? 'custom';
        $tagData['color'] = $tagData['color'] ?? '#007bff';

        $tag = ContactTag::create($tagData);

        return response()->json([
            'success' => true,
            'message' => 'Contact tag created successfully',
            'data' => $tag,
        ], 201);
    }

    /**
     * Display the specified contact tag
     */
    public function show(ContactTag $contactTag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $contactTag,
            'contacts_count' => $contactTag->getContactsWithTag()->count(),
        ]);
    }

    /**
     * Update the specified contact tag
     */
    public function update(Request $request, ContactTag $contactTag): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255|unique:contact_tags,name,' . $contactTag->id,
            'color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'description' => 'nullable|string',
            'type' => 'in:system,custom',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contactTag->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Contact tag updated successfully',
            'data' => $contactTag->fresh(),
        ]);
    }

    /**
     * Remove the specified contact tag
     */
    public function destroy(ContactTag $contactTag): JsonResponse
    {
        // Remove this tag from all contacts that have it
        $contacts = $contactTag->getContactsWithTag();
        foreach ($contacts as $contact) {
            $contact->removeTag($contactTag->name);
        }

        $contactTag->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact tag deleted successfully',
        ]);
    }

    /**
     * Get contacts that have this tag
     */
    public function contacts(Request $request, ContactTag $contactTag): JsonResponse
    {
        $query = $contactTag->getContactsWithTag();

        // Apply additional filters if needed
        if ($request->has('status')) {
            $query = $query->where('status', $request->status);
        }

        if ($request->has('newsletter_subscribed')) {
            $query = $query->where('newsletter_subscribed', $request->boolean('newsletter_subscribed'));
        }

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
     * Get popular tags
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 50);
        $tags = ContactTag::popular($limit);

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Apply tag to multiple contacts
     */
    public function applyToContacts(Request $request, ContactTag $contactTag): JsonResponse
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
        $contacts = Contact::whereIn('id', $contactIds)->get();

        $applied = 0;
        foreach ($contacts as $contact) {
            if (!$contact->hasTag($contactTag->name)) {
                $contact->addTag($contactTag->name);
                $applied++;
            }
        }

        // Update usage count
        if ($applied > 0) {
            $contactTag->usage_count += $applied;
            $contactTag->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Tag applied to {$applied} contacts",
            'applied_count' => $applied,
        ]);
    }

    /**
     * Remove tag from multiple contacts
     */
    public function removeFromContacts(Request $request, ContactTag $contactTag): JsonResponse
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
        $contacts = Contact::whereIn('id', $contactIds)->get();

        $removed = 0;
        foreach ($contacts as $contact) {
            if ($contact->hasTag($contactTag->name)) {
                $contact->removeTag($contactTag->name);
                $removed++;
            }
        }

        // Update usage count
        if ($removed > 0) {
            $contactTag->usage_count = max(0, $contactTag->usage_count - $removed);
            $contactTag->save();
        }

        return response()->json([
            'success' => true,
            'message' => "Tag removed from {$removed} contacts",
            'removed_count' => $removed,
        ]);
    }

    /**
     * Get tag statistics
     */
    public function stats(ContactTag $contactTag): JsonResponse
    {
        $contacts = $contactTag->getContactsWithTag();

        $stats = [
            'total_contacts' => $contacts->count(),
            'active_contacts' => $contacts->where('status', 'active')->count(),
            'subscribed_contacts' => $contacts->where('newsletter_subscribed', true)->count(),
            'marketing_subscribed' => $contacts->where('marketing_subscribed', true)->count(),
            'recent_additions' => $contacts->where('created_at', '>=', now()->subDays(7))->count(),
            'countries' => $contacts->whereNotNull('country')
                                   ->groupBy('country')
                                   ->selectRaw('country, count(*) as count')
                                   ->pluck('count', 'country')
                                   ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Merge tags
     */
    public function merge(Request $request, ContactTag $contactTag): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'target_tag_id' => 'required|integer|exists:contact_tags,id|different:id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $targetTag = ContactTag::findOrFail($validator->validated()['target_tag_id']);

        // Get all contacts with the source tag
        $contacts = $contactTag->getContactsWithTag();
        $merged = 0;

        foreach ($contacts as $contact) {
            // Remove source tag and add target tag
            $contact->removeTag($contactTag->name);
            if (!$contact->hasTag($targetTag->name)) {
                $contact->addTag($targetTag->name);
                $merged++;
            }
        }

        // Update target tag usage count
        $targetTag->usage_count += $merged;
        $targetTag->save();

        // Delete source tag
        $contactTag->delete();

        return response()->json([
            'success' => true,
            'message' => "Tag merged successfully. {$merged} contacts moved to '{$targetTag->name}'",
            'merged_count' => $merged,
        ]);
    }
}