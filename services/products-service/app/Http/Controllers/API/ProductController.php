<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Type;
use App\Models\Category;
use App\Models\Catalog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['brand', 'types', 'categories', 'catalogs', 'attributes.attributeGroup', 'characteristics.relatedCharacteristic'])
                       ->orderBy('created_at', 'desc');

        // Apply filters
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('ref', 'like', "%{$search}%");
        }

        if ($brandId = $request->get('brand_id')) {
            $query->where('id_1', $brandId);
        }

        if ($categoryId = $request->get('category_id')) {
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId);
            });
        }

        if ($typeId = $request->get('type_id')) {
            $query->whereHas('types', function ($q) use ($typeId) {
                $q->where('types.id', $typeId);
            });
        }

        if ($catalogId = $request->get('catalog_id')) {
            $query->whereHas('catalogs', function ($q) use ($catalogId) {
                $q->where('catalogs.id', $catalogId);
            });
        }

        if ($request->get('in_stock') === 'true') {
            $query->where('stock', '>', 0);
        }

        // Price range filter
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        if ($minPrice) {
            $query->where('price_ht', '>=', $minPrice);
        }
        if ($maxPrice) {
            $query->where('price_ht', '<=', $maxPrice);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name', 'ref', 'price_ht', 'stock', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'has_more' => $products->hasMorePages()
            ]
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'ref' => 'required|string|max:255|unique:products',
            'price_ht' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'id_1' => 'nullable|exists:brands,id', // brand_id
            'type_ids' => 'nullable|array',
            'type_ids.*' => 'exists:types,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'catalog_ids' => 'nullable|array',
            'catalog_ids.*' => 'exists:catalogs,id',
        ]);

        $typeIds = $validatedData['type_ids'] ?? [];
        $categoryIds = $validatedData['category_ids'] ?? [];
        $catalogIds = $validatedData['catalog_ids'] ?? [];
        
        unset($validatedData['type_ids'], $validatedData['category_ids'], $validatedData['catalog_ids']);

        $product = Product::create($validatedData);

        // Attach relationships
        if (!empty($typeIds)) {
            $product->types()->attach($typeIds);
        }
        if (!empty($categoryIds)) {
            $product->categories()->attach($categoryIds);
        }
        if (!empty($catalogIds)) {
            $product->catalogs()->attach($catalogIds);
        }

        $product->load(['brand', 'types', 'categories', 'catalogs']);

        return response()->json([
            'data' => $product,
            'message' => 'Product created successfully'
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['brand', 'types', 'categories', 'catalogs', 'attributes.attributeGroup', 'characteristics.relatedCharacteristic']);

        return response()->json(['data' => $product]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ref' => ['sometimes', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'price_ht' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'id_1' => 'nullable|exists:brands,id', // brand_id
            'type_ids' => 'nullable|array',
            'type_ids.*' => 'exists:types,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'catalog_ids' => 'nullable|array',
            'catalog_ids.*' => 'exists:catalogs,id',
        ]);

        $typeIds = $validatedData['type_ids'] ?? null;
        $categoryIds = $validatedData['category_ids'] ?? null;
        $catalogIds = $validatedData['catalog_ids'] ?? null;
        
        unset($validatedData['type_ids'], $validatedData['category_ids'], $validatedData['catalog_ids']);

        $product->update($validatedData);

        // Update relationships if provided
        if ($typeIds !== null) {
            $product->types()->sync($typeIds);
        }
        if ($categoryIds !== null) {
            $product->categories()->sync($categoryIds);
        }
        if ($catalogIds !== null) {
            $product->catalogs()->sync($catalogIds);
        }

        $product->load(['brand', 'types', 'categories', 'catalogs']);

        return response()->json([
            'data' => $product,
            'message' => 'Product updated successfully'
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * Update product stock.
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validatedData = $request->validate([
            'stock' => 'required|integer|min:0',
            'operation' => ['required', Rule::in(['set', 'increment', 'decrement'])]
        ]);

        $operation = $validatedData['operation'];
        $stock = $validatedData['stock'];

        switch ($operation) {
            case 'set':
                $product->update(['stock' => $stock]);
                break;
            case 'increment':
                $product->increment('stock', $stock);
                break;
            case 'decrement':
                if ($product->stock < $stock) {
                    return response()->json(['error' => 'Insufficient stock'], 400);
                }
                $product->decrement('stock', $stock);
                break;
        }

        $product->refresh();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'stock' => $product->stock,
                'in_stock' => $product->stock > 0
            ],
            'message' => 'Stock updated successfully'
        ]);
    }

    /**
     * Search products.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = $request->get('q');
        $limit = min($request->get('limit', 20), 50);

        $products = Product::with(['brand', 'types', 'categories', 'catalogs'])
                          ->where('name', 'like', "%{$query}%")
                          ->orWhere('ref', 'like', "%{$query}%")
                          ->orderBy('name')
                          ->limit($limit)
                          ->get();

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
                'query' => $query
            ]
        ]);
    }
}