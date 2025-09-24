<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\SalePoint;
use App\Models\Status;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

class DeliveryController extends Controller
{
    /**
     * Get deliveries for authenticated user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $deliveries = Delivery::with(['salePoint', 'status'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $deliveries,
        ]);
    }

    /**
     * Get a specific delivery.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::with(['salePoint', 'status'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $delivery,
        ]);
    }

    /**
     * Track a delivery by tracking number.
     */
    public function track(Request $request, $trackingNumber): JsonResponse
    {
        $delivery = Delivery::with(['salePoint', 'status'])
            ->where('tracking_number', $trackingNumber)
            ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found with this tracking number'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $delivery,
        ]);
    }

    /**
     * Create a new delivery (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'sale_point_id' => 'required|exists:sale_points,id',
            'status_id' => 'required|exists:status,id',
            'delivery_method' => 'required|in:standard,express,pickup',
            'shipping_cost' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'estimated_delivery_date' => 'nullable|date|after:today',
            'carrier_name' => 'nullable|string|max:255',
            'carrier_tracking_number' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $delivery = Delivery::create($validator->validated());
        $delivery->load(['salePoint', 'status']);

        return response()->json([
            'success' => true,
            'data' => $delivery,
            'message' => 'Delivery created successfully'
        ], 201);
    }

    /**
     * Update a delivery (Admin only).
     */
    public function update(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sale_point_id' => 'nullable|exists:sale_points,id',
            'status_id' => 'nullable|exists:status,id',
            'delivery_method' => 'nullable|in:standard,express,pickup',
            'shipping_cost' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'estimated_delivery_date' => 'nullable|date',
            'actual_delivery_date' => 'nullable|date',
            'shipped_at' => 'nullable|date',
            'carrier_name' => 'nullable|string|max:255',
            'carrier_tracking_number' => 'nullable|string|max:255',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
            'delivery_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $delivery->update($validator->validated());
        $delivery->load(['salePoint', 'status']);

        return response()->json([
            'success' => true,
            'data' => $delivery,
            'message' => 'Delivery updated successfully'
        ]);
    }

    /**
     * Update delivery status.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:status,id',
            'delivery_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $delivery->updateStatus(
            $request->status_id,
            $request->delivery_notes
        );

        $delivery->load(['salePoint', 'status']);

        return response()->json([
            'success' => true,
            'data' => $delivery,
            'message' => 'Delivery status updated successfully'
        ]);
    }

    /**
     * Get delivery statistics.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_deliveries' => Delivery::count(),
            'pending_deliveries' => Delivery::pending()->count(),
            'shipped_deliveries' => Delivery::shipped()->count(),
            'delivered_deliveries' => Delivery::delivered()->count(),
            'overdue_deliveries' => Delivery::overdue()->count(),
            'by_method' => [
                'standard' => Delivery::byMethod('standard')->count(),
                'express' => Delivery::byMethod('express')->count(),
                'pickup' => Delivery::byMethod('pickup')->count(),
            ],
            'by_status' => Status::withCount('deliveries')->get()->map(function ($status) {
                return [
                    'status' => $status->name,
                    'count' => $status->deliveries_count,
                    'color' => $status->formatted_color,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Create delivery from order (integration with orders-service).
     */
    public function createFromOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'sale_point_id' => 'required|exists:sale_points,id',
            'delivery_method' => 'required|in:standard,express,pickup',
            'shipping_cost' => 'nullable|numeric|min:0',
            'delivery_address' => 'nullable|string',
            'special_instructions' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
            'recipient_phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get default "pending" status
        $pendingStatus = Status::where('name', 'pending')->first();
        if (!$pendingStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Default pending status not found'
            ], 500);
        }

        // Calculate estimated delivery date based on delivery method
        $estimatedDate = match ($request->delivery_method) {
            'express' => now()->addDays(1),
            'standard' => now()->addDays(3),
            'pickup' => now()->addDays(2),
            default => now()->addDays(3),
        };

        $deliveryData = array_merge($validator->validated(), [
            'status_id' => $pendingStatus->id,
            'estimated_delivery_date' => $estimatedDate,
        ]);

        $delivery = Delivery::create($deliveryData);
        $delivery->load(['salePoint', 'status']);

        return response()->json([
            'success' => true,
            'data' => $delivery,
            'message' => 'Delivery created from order successfully'
        ], 201);
    }

    /**
     * Delete a delivery (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $delivery->delete();

        return response()->json([
            'success' => true,
            'message' => 'Delivery deleted successfully'
        ]);
    }
}