<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    /**
     * Display a listing of catalogs.
     */
    public function index(): JsonResponse
    {
        $catalogs = Catalog::withCount('products')->orderBy('name')->get();

        return response()->json(['data' => $catalogs]);
    }

    /**
     * Store a newly created catalog.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:catalogs,name',
        ]);

        $catalog = Catalog::create($validatedData);

        return response()->json([
            'data' => $catalog,
            'message' => 'Catalog created successfully'
        ], 201);
    }

    /**
     * Display the specified catalog.
     */
    public function show(Catalog $catalog): JsonResponse
    {
        $catalog->loadCount('products');

        return response()->json(['data' => $catalog]);
    }

    /**
     * Update the specified catalog.
     */
    public function update(Request $request, Catalog $catalog): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:catalogs,name,' . $catalog->id,
        ]);

        $catalog->update($validatedData);

        return response()->json([
            'data' => $catalog,
            'message' => 'Catalog updated successfully'
        ]);
    }

    /**
     * Remove the specified catalog.
     */
    public function destroy(Catalog $catalog): JsonResponse
    {
        if ($catalog->products()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete catalog with associated products'
            ], 400);
        }

        $catalog->delete();

        return response()->json(['message' => 'Catalog deleted successfully']);
    }

    /**
     * Get products for a specific catalog.
     */
    public function products(Catalog $catalog): JsonResponse
    {
        $products = $catalog->products()->with(['brand', 'types', 'categories'])->get();

        return response()->json(['data' => $products]);
    }
}