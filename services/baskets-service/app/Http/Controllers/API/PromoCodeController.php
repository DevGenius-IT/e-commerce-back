<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromoCodeController extends Controller
{
    /**
     * Display a listing of promo codes.
     */
    public function index(): JsonResponse
    {
        $promoCodes = PromoCode::with('type')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $promoCodes
        ]);
    }

    /**
     * Store a newly created promo code.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:promo_codes',
            'discount' => 'required|numeric|min:0',
            'id_1' => 'nullable|exists:types,id'
        ]);

        $promoCode = PromoCode::create($request->all());
        $promoCode->load('type');

        return response()->json([
            'success' => true,
            'message' => 'Promo code created successfully',
            'data' => $promoCode
        ], 201);
    }

    /**
     * Display the specified promo code.
     */
    public function show(string $id): JsonResponse
    {
        $promoCode = PromoCode::with('type')->find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $promoCode
        ]);
    }

    /**
     * Update the specified promo code.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $promoCode = PromoCode::find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $id,
            'discount' => 'sometimes|numeric|min:0',
            'id_1' => 'nullable|exists:types,id'
        ]);

        $promoCode->update($request->all());
        $promoCode->load('type');

        return response()->json([
            'success' => true,
            'message' => 'Promo code updated successfully',
            'data' => $promoCode
        ]);
    }

    /**
     * Validate a promo code by code.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $promoCode = PromoCode::where('code', $request->code)->with('type')->first();

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid promo code'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Promo code is valid',
            'data' => $promoCode
        ]);
    }

    /**
     * Remove the specified promo code.
     */
    public function destroy(string $id): JsonResponse
    {
        $promoCode = PromoCode::find($id);

        if (!$promoCode) {
            return response()->json([
                'success' => false,
                'message' => 'Promo code not found'
            ], 404);
        }

        $promoCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Promo code deleted successfully'
        ]);
    }
}