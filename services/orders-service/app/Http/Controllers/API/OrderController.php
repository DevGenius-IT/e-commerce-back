<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        // Temporarily get all orders for testing (normally filtered by user)
        $orders = Order::with(['status', 'orderItems'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get a specific order for the authenticated user.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->get('auth_user');
        
        $order = Order::with(['status', 'orderItems'])
            ->forUser($user['id'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Create a new order from basket.
     */
    public function createFromBasket(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'basket_id' => 'required|integer',
            'billing_address_id' => 'required|integer',
            'shipping_address_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->get('auth_user');

        try {
            // Get basket data from baskets service
            $basketResponse = Http::withHeaders([
                'Authorization' => $request->header('Authorization'),
                'Accept' => 'application/json',
            ])->get(env('BASKETS_SERVICE_URL', 'http://baskets-service:8005') . '/api/baskets/current');

            if (!$basketResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to retrieve basket data'
                ], 400);
            }

            $basketData = $basketResponse->json()['data'];
            
            if (empty($basketData['items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Basket is empty'
                ], 400);
            }

            DB::beginTransaction();

            // Get pending status
            $pendingStatus = OrderStatus::where('name', 'pending')->first();
            
            // Create order
            $order = Order::create([
                'user_id' => $user['id'],
                'billing_address_id' => $request->billing_address_id,
                'shipping_address_id' => $request->shipping_address_id ?: $request->billing_address_id,
                'status_id' => $pendingStatus->id,
                'notes' => $request->notes,
                'total_discount' => $basketData['promo_codes'] ? 
                    collect($basketData['promo_codes'])->sum('pivot.discount_amount') : 0,
            ]);

            // Create order items from basket items
            foreach ($basketData['items'] as $basketItem) {
                // Get product data from products service
                $productResponse = Http::withHeaders([
                    'Authorization' => $request->header('Authorization'),
                    'Accept' => 'application/json',
                ])->get(env('PRODUCTS_SERVICE_URL', 'http://products-service:8003') . '/api/products/' . $basketItem['product_id']);

                if ($productResponse->successful()) {
                    $productData = $productResponse->json()['data'];
                    
                    // Calculate VAT
                    $vatRate = $productData['vat_rate'] ?? 20.0;
                    $unitPriceHt = $basketItem['price_ht'];
                    $unitPriceTtc = $unitPriceHt * (1 + $vatRate / 100);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $basketItem['product_id'],
                        'quantity' => $basketItem['quantity'],
                        'unit_price_ht' => $unitPriceHt,
                        'unit_price_ttc' => $unitPriceTtc,
                        'vat_rate' => $vatRate,
                        'product_name' => $productData['name'],
                        'product_ref' => $productData['ref'],
                    ]);
                }
            }

            // Clear the basket after order creation
            Http::withHeaders([
                'Authorization' => $request->header('Authorization'),
                'Accept' => 'application/json',
            ])->delete(env('BASKETS_SERVICE_URL', 'http://baskets-service:8005') . '/api/baskets/clear');

            DB::commit();

            // Load the order with relationships
            $order->load(['status', 'orderItems']);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status (for authorized users only).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status_id' => 'required|exists:order_status,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->get('auth_user');
        
        $order = Order::forUser($user['id'])->findOrFail($id);
        
        $order->update([
            'status_id' => $request->status_id
        ]);

        $order->load(['status', 'orderItems']);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order,
        ]);
    }

    /**
     * Cancel an order (if allowed).
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $user = $request->get('auth_user');
        
        $order = Order::with('status')->forUser($user['id'])->findOrFail($id);
        
        if (!$order->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be cancelled'
            ], 400);
        }

        $cancelledStatus = OrderStatus::where('name', 'cancelled')->first();
        
        $order->update([
            'status_id' => $cancelledStatus->id
        ]);

        $order->load(['status', 'orderItems']);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully',
            'data' => $order,
        ]);
    }

    // Admin methods

    /**
     * Get all orders (admin only).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $orders = Order::with(['status', 'orderItems'])
            ->when($request->status, function ($query, $status) {
                return $query->byStatus($status);
            })
            ->when($request->user_id, function ($query, $userId) {
                return $query->forUser($userId);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Get any order by ID (admin only).
     */
    public function adminShow($id): JsonResponse
    {
        $order = Order::with(['status', 'orderItems'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * Update any order (admin only).
     */
    public function adminUpdate(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status_id' => 'sometimes|exists:order_status,id',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::findOrFail($id);
        
        $order->update($request->only(['status_id', 'notes']));

        $order->load(['status', 'orderItems']);

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order,
        ]);
    }

    /**
     * Delete an order (admin only).
     */
    public function adminDestroy($id): JsonResponse
    {
        $order = Order::findOrFail($id);
        
        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }
}