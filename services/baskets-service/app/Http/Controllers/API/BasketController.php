<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Basket;
use App\Models\BasketItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BasketController extends Controller
{
    /**
     * Display a listing of all baskets (Admin only).
     */
    public function index(): JsonResponse
    {
        $baskets = Basket::with(['items', 'promoCodes'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $baskets
        ]);
    }

    /**
     * Get current user's basket.
     */
    public function current(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        $basket = Basket::with(['items', 'promoCodes'])
            ->where('user_id', $userId)
            ->first();

        if (!$basket) {
            $basket = Basket::create([
                'user_id' => $userId,
                'amount' => 0
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $basket
        ]);
    }

    /**
     * Display the specified basket.
     */
    public function show(string $id): JsonResponse
    {
        $basket = Basket::with(['items', 'promoCodes'])->find($id);

        if (!$basket) {
            return response()->json([
                'success' => false,
                'message' => 'Basket not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $basket
        ]);
    }

    /**
     * Add item to current user's basket.
     */
    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
            'price_ht' => 'required|numeric|min:0'
        ]);

        $userId = $request->user()->id;
        
        $basket = Basket::firstOrCreate(['user_id' => $userId], ['amount' => 0]);

        // Check if item already exists in basket
        $existingItem = BasketItem::where('basket_id', $basket->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $request->quantity;
            $existingItem->save();
        } else {
            BasketItem::create([
                'basket_id' => $basket->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price_ht' => $request->price_ht
            ]);
        }

        $basket->load(['items', 'promoCodes']);

        return response()->json([
            'success' => true,
            'message' => 'Item added to basket',
            'data' => $basket
        ]);
    }

    /**
     * Update item quantity in basket.
     */
    public function updateItem(Request $request, string $itemId): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $userId = $request->user()->id;
        
        $item = BasketItem::whereHas('basket', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->find($itemId);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $item->quantity = $request->quantity;
        $item->save();

        $basket = $item->basket->load(['items', 'promoCodes']);

        return response()->json([
            'success' => true,
            'message' => 'Item updated',
            'data' => $basket
        ]);
    }

    /**
     * Remove item from basket.
     */
    public function removeItem(Request $request, string $itemId): JsonResponse
    {
        $userId = $request->user()->id;
        
        $item = BasketItem::whereHas('basket', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->find($itemId);

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $basket = $item->basket;
        $item->delete();

        $basket->load(['items', 'promoCodes']);

        return response()->json([
            'success' => true,
            'message' => 'Item removed from basket',
            'data' => $basket
        ]);
    }

    /**
     * Apply promo code to basket.
     */
    public function applyPromoCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $userId = $request->user()->id;
        $basket = Basket::where('user_id', $userId)->first();

        if (!$basket) {
            return response()->json([
                'success' => false,
                'message' => 'Basket not found'
            ], 404);
        }

        $promoCode = \App\Models\PromoCode::where('code', $request->code)->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code'
            ], 400);
        }

        if ($basket->promoCodes()->where('promo_code_id', $promoCode->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code already applied'
            ], 400);
        }

        $basket->promoCodes()->attach($promoCode->id);
        $basket->calculateTotal();
        $basket->load(['items', 'promoCodes']);

        return response()->json([
            'success' => true,
            'message' => 'Promo code applied',
            'data' => $basket
        ]);
    }

    /**
     * Remove promo code from basket.
     */
    public function removePromoCode(Request $request, string $promoCodeId): JsonResponse
    {
        $userId = $request->user()->id;
        $basket = Basket::where('user_id', $userId)->first();

        if (!$basket) {
            return response()->json([
                'success' => false,
                'message' => 'Basket not found'
            ], 404);
        }

        $basket->promoCodes()->detach($promoCodeId);
        $basket->calculateTotal();
        $basket->load(['items', 'promoCodes']);

        return response()->json([
            'success' => true,
            'message' => 'Promo code removed',
            'data' => $basket
        ]);
    }

    /**
     * Clear user's basket.
     */
    public function clear(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $basket = Basket::where('user_id', $userId)->first();

        if (!$basket) {
            return response()->json([
                'success' => false,
                'message' => 'Basket not found'
            ], 404);
        }

        $basket->items()->delete();
        $basket->promoCodes()->detach();
        $basket->update(['amount' => 0]);

        return response()->json([
            'success' => true,
            'message' => 'Basket cleared',
            'data' => $basket
        ]);
    }

    /**
     * Delete a basket (Admin only).
     */
    public function destroy(string $id): JsonResponse
    {
        $basket = Basket::find($id);

        if (!$basket) {
            return response()->json([
                'success' => false,
                'message' => 'Basket not found'
            ], 404);
        }

        $basket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Basket deleted successfully'
        ]);
    }
}