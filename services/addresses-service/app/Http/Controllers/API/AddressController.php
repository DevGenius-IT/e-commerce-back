<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AddressController extends Controller
{
    /**
     * Display a listing of addresses for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        $addresses = Address::with(['country', 'region'])
            ->forUser($userId)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $addresses,
            'meta' => [
                'total' => $addresses->count()
            ]
        ]);
    }

    /**
     * Store a newly created address.
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        $validatedData = $request->validate([
            'type' => ['required', Rule::in(['billing', 'shipping', 'both'])],
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'region_id' => 'nullable|exists:regions,id',
            'country_id' => 'required|exists:countries,id',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        $validatedData['user_id'] = $userId;

        // If this is set as default, remove default from other addresses
        if ($validatedData['is_default'] ?? false) {
            Address::forUser($userId)->update(['is_default' => false]);
        }

        $address = Address::create($validatedData);
        $address->load(['country', 'region']);

        return response()->json([
            'data' => $address,
            'message' => 'Address created successfully'
        ], 201);
    }

    /**
     * Display the specified address.
     */
    public function show(Request $request, Address $address): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        // Ensure user owns this address
        if ($address->user_id !== $userId) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        $address->load(['country', 'region']);

        return response()->json(['data' => $address]);
    }

    /**
     * Update the specified address.
     */
    public function update(Request $request, Address $address): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        // Ensure user owns this address
        if ($address->user_id !== $userId) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        $validatedData = $request->validate([
            'type' => ['sometimes', Rule::in(['billing', 'shipping', 'both'])],
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'company' => 'nullable|string|max:255',
            'address_line_1' => 'sometimes|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'region_id' => 'nullable|exists:regions,id',
            'country_id' => 'sometimes|exists:countries,id',
            'phone' => 'nullable|string|max:20',
            'is_default' => 'boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180'
        ]);

        // If this is set as default, remove default from other addresses
        if (isset($validatedData['is_default']) && $validatedData['is_default']) {
            Address::forUser($userId)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
        }

        $address->update($validatedData);
        $address->load(['country', 'region']);

        return response()->json([
            'data' => $address,
            'message' => 'Address updated successfully'
        ]);
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Request $request, Address $address): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        // Ensure user owns this address
        if ($address->user_id !== $userId) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        // If this was the default address, make the first remaining address default
        if ($address->is_default) {
            $nextDefault = Address::forUser($userId)
                ->where('id', '!=', $address->id)
                ->first();
            
            if ($nextDefault) {
                $nextDefault->update(['is_default' => true]);
            }
        }

        $address->delete();

        return response()->json(['message' => 'Address deleted successfully']);
    }

    /**
     * Set an address as the default for the user.
     */
    public function setDefault(Request $request, Address $address): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        // Ensure user owns this address
        if ($address->user_id !== $userId) {
            return response()->json(['error' => 'Address not found'], 404);
        }

        // Remove default from all user addresses
        Address::forUser($userId)->update(['is_default' => false]);
        
        // Set this address as default
        $address->update(['is_default' => true]);
        $address->load(['country', 'region']);

        return response()->json([
            'data' => $address,
            'message' => 'Address set as default successfully'
        ]);
    }

    /**
     * Get addresses by type (billing or shipping).
     */
    public function byType(Request $request, string $type): JsonResponse
    {
        $userId = $this->getUserId($request);
        
        if (!in_array($type, ['billing', 'shipping'])) {
            return response()->json(['error' => 'Invalid address type'], 400);
        }

        $addresses = Address::with(['country', 'region'])
            ->forUser($userId)
            ->{$type}()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $addresses,
            'meta' => [
                'total' => $addresses->count(),
                'type' => $type
            ]
        ]);
    }

    /**
     * Extract user ID from request (from gateway or auth middleware).
     */
    protected function getUserId(Request $request): int
    {
        // Try to get from X-Auth-User header (from gateway)
        if ($authUser = $request->header('X-Auth-User')) {
            $user = json_decode(base64_decode($authUser), true);
            return $user['id'] ?? 1; // fallback to user ID 1 for testing
        }

        // Try to get from authenticated user
        if (auth()->check()) {
            return auth()->id();
        }

        // Fallback for development/testing
        return 1;
    }
}