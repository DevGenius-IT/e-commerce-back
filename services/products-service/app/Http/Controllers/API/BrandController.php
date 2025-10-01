<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     */
    public function index(): JsonResponse
    {
        $brands = Brand::withCount('products')->orderBy('name')->get();

        return response()->json(['data' => $brands]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
        ]);

        $brand = Brand::create($validatedData);

        return response()->json([
            'data' => $brand,
            'message' => 'Brand created successfully'
        ], 201);
    }

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        $brand->loadCount('products');

        return response()->json(['data' => $brand]);
    }

    /**
     * Update the specified brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:brands,name,' . $brand->id,
        ]);

        $brand->update($validatedData);

        return response()->json([
            'data' => $brand,
            'message' => 'Brand updated successfully'
        ]);
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        if ($brand->products()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete brand with associated products'
            ], 400);
        }

        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully']);
    }

    /**
     * Get products for a specific brand.
     */
    public function products(Brand $brand): JsonResponse
    {
        $products = $brand->products()->with(['types', 'categories', 'catalogs'])->get();

        return response()->json(['data' => $products]);
    }
}