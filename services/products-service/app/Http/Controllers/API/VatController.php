<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Vat;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VatController extends Controller
{
    /**
     * Display a listing of VAT rates.
     */
    public function index(): JsonResponse
    {
        $vatRates = Vat::orderBy('name')->get();

        return response()->json(['data' => $vatRates]);
    }

    /**
     * Store a newly created VAT rate.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:vat,name',
            'value_' => 'required|numeric|min:0|max:100',
        ]);

        $vat = Vat::create($validatedData);

        return response()->json([
            'data' => $vat,
            'message' => 'VAT rate created successfully'
        ], 201);
    }

    /**
     * Display the specified VAT rate.
     */
    public function show(Vat $vat): JsonResponse
    {
        return response()->json(['data' => $vat]);
    }

    /**
     * Update the specified VAT rate.
     */
    public function update(Request $request, Vat $vat): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:vat,name,' . $vat->id,
            'value_' => 'required|numeric|min:0|max:100',
        ]);

        $vat->update($validatedData);

        return response()->json([
            'data' => $vat,
            'message' => 'VAT rate updated successfully'
        ]);
    }

    /**
     * Remove the specified VAT rate.
     */
    public function destroy(Vat $vat): JsonResponse
    {
        $vat->delete();

        return response()->json(['message' => 'VAT rate deleted successfully']);
    }
}