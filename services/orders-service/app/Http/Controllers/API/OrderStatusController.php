<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class OrderStatusController extends Controller
{
    /**
     * Get all order statuses.
     */
    public function index(): JsonResponse
    {
        $statuses = OrderStatus::withCount('orders')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Get a specific order status.
     */
    public function show($id): JsonResponse
    {
        $status = OrderStatus::withCount('orders')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Create a new order status (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:order_status,name',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = OrderStatus::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status created successfully',
            'data' => $status,
        ], 201);
    }

    /**
     * Update an order status (admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:order_status,name,' . $id,
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = OrderStatus::findOrFail($id);
        
        $status->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $status,
        ]);
    }

    /**
     * Delete an order status (admin only).
     */
    public function destroy($id): JsonResponse
    {
        $status = OrderStatus::withCount('orders')->findOrFail($id);

        // Check if status is being used by orders
        if ($status->orders_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete status that is being used by orders',
            ], 400);
        }

        // Prevent deletion of core statuses
        $coreStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        if (in_array(strtolower($status->name), $coreStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete core status',
            ], 400);
        }

        $status->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order status deleted successfully',
        ]);
    }

    /**
     * Get orders statistics by status.
     */
    public function statistics(): JsonResponse
    {
        $statistics = OrderStatus::withCount('orders')
            ->get()
            ->map(function ($status) {
                return [
                    'id' => $status->id,
                    'name' => $status->name,
                    'description' => $status->description,
                    'orders_count' => $status->orders_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $statistics,
        ]);
    }
}