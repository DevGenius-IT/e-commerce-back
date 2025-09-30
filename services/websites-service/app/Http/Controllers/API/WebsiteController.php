<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class WebsiteController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'OK',
            'service' => 'Websites Service',
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');

        $query = Website::query();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        $websites = $query->latest()->paginate($perPage);

        return response()->json($websites);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:websites,domain',
            ]);

            $website = Website::create($validated);

            return response()->json([
                'message' => 'Website created successfully',
                'data' => $website
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Website $website): JsonResponse
    {
        return response()->json([
            'data' => $website
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Website $website): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'domain' => 'sometimes|required|string|max:255|unique:websites,domain,' . $website->id,
            ]);

            $website->update($validated);

            return response()->json([
                'message' => 'Website updated successfully',
                'data' => $website->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Website $website): JsonResponse
    {
        $website->delete();

        return response()->json([
            'message' => 'Website deleted successfully'
        ]);
    }

    /**
     * Search websites
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');
        $perPage = $request->get('per_page', 15);

        if (empty($search)) {
            return response()->json([
                'message' => 'Search query is required',
                'data' => []
            ]);
        }

        $websites = Website::where('name', 'like', "%{$search}%")
            ->orWhere('domain', 'like', "%{$search}%")
            ->latest()
            ->paginate($perPage);

        return response()->json($websites);
    }
}