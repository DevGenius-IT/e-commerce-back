<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class StatusController extends Controller
{
    /**
     * Get all delivery statuses.
     */
    public function index(): JsonResponse
    {
        $statuses = Status::withCount('deliveries')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Get a specific status.
     */
    public function show($id): JsonResponse
    {
        $status = Status::withCount('deliveries')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Create a new status (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:status,name',
            'color' => 'required|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Status::create($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $status,
            'message' => 'Status created successfully'
        ], 201);
    }

    /**
     * Update a status (Admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $status = Status::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255|unique:status,name,' . $id,
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $status->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $status,
            'message' => 'Status updated successfully'
        ]);
    }

    /**
     * Delete a status (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        $status = Status::findOrFail($id);
        
        // Check if there are deliveries with this status
        if ($status->deliveries_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete status that is being used by deliveries'
            ], 422);
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Status deleted successfully'
        ]);
    }

    /**
     * Get status statistics.
     */
    public function statistics(): JsonResponse
    {
        $statuses = Status::withCount('deliveries')
            ->get()
            ->map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->formatted_color,
                    'description' => $status->description,
                    'deliveries_count' => $status->deliveries_count,
                    'percentage' => 0, // Will be calculated below
                ];
            });

        $totalDeliveries = $statuses->sum('deliveries_count');

        // Calculate percentages
        $statuses = $statuses->map(function ($status) use ($totalDeliveries) {
            $status['percentage'] = $totalDeliveries > 0 
                ? round(($status['deliveries_count'] / $totalDeliveries) * 100, 2)
                : 0;
            return $status;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => $statuses,
                'total_deliveries' => $totalDeliveries,
                'total_statuses' => $statuses->count(),
            ]
        ]);
    }
}