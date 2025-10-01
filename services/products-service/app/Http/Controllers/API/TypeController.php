<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TypeController extends Controller
{
    /**
     * Display a listing of types.
     */
    public function index(): JsonResponse
    {
        $types = Type::withCount('products')->orderBy('name')->get();

        return response()->json(['data' => $types]);
    }

    /**
     * Store a newly created type.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:types,name',
        ]);

        $type = Type::create($validatedData);

        return response()->json([
            'data' => $type,
            'message' => 'Type created successfully'
        ], 201);
    }

    /**
     * Display the specified type.
     */
    public function show(Type $type): JsonResponse
    {
        $type->loadCount('products');

        return response()->json(['data' => $type]);
    }

    /**
     * Update the specified type.
     */
    public function update(Request $request, Type $type): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:types,name,' . $type->id,
        ]);

        $type->update($validatedData);

        return response()->json([
            'data' => $type,
            'message' => 'Type updated successfully'
        ]);
    }

    /**
     * Remove the specified type.
     */
    public function destroy(Type $type): JsonResponse
    {
        if ($type->products()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete type with associated products'
            ], 400);
        }

        $type->delete();

        return response()->json(['message' => 'Type deleted successfully']);
    }

    /**
     * Get products for a specific type.
     */
    public function products(Type $type): JsonResponse
    {
        $products = $type->products()->with(['brand', 'categories', 'catalogs'])->get();

        return response()->json(['data' => $products]);
    }
}