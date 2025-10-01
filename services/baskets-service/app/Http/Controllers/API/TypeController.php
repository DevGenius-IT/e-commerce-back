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
        $types = Type::with('promoCodes')
            ->orderBy('name')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Store a newly created type.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10'
        ]);

        $type = Type::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Type created successfully',
            'data' => $type
        ], 201);
    }

    /**
     * Display the specified type.
     */
    public function show(string $id): JsonResponse
    {
        $type = Type::with('promoCodes')->find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $type
        ]);
    }

    /**
     * Update the specified type.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $type = Type::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'symbol' => 'sometimes|string|max:10'
        ]);

        $type->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Type updated successfully',
            'data' => $type
        ]);
    }

    /**
     * Remove the specified type.
     */
    public function destroy(string $id): JsonResponse
    {
        $type = Type::find($id);

        if (!$type) {
            return response()->json([
                'success' => false,
                'message' => 'Type not found'
            ], 404);
        }

        // Check if type has associated promo codes
        if ($type->promoCodes()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete type that has associated promo codes'
            ], 400);
        }

        $type->delete();

        return response()->json([
            'success' => true,
            'message' => 'Type deleted successfully'
        ]);
    }
}