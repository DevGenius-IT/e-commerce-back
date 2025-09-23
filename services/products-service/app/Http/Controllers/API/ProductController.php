<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
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
        $query = Product::with(['categories', 'images'])
                       ->active()
                       ->orderBy('created_at', 'desc');

        // Apply filters
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        if ($categoryId = $request->get('category_id')) {
            $query->inCategory($categoryId);
        }

        if ($request->get('featured') === 'true') {
            $query->featured();
        }

        if ($request->get('in_stock') === 'true') {
            $query->inStock();
        }

        // Price range filter
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        if ($minPrice || $maxPrice) {
            $query->priceRange($minPrice, $maxPrice);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        if (in_array($sortBy, ['name', 'price', 'created_at'])) {
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
            'sku' => 'required|string|max:255|unique:products',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0|gt:price',
            'cost' => 'nullable|numeric|min:0',
            'track_quantity' => 'boolean',
            'quantity' => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'dimensions.length' => 'nullable|numeric|min:0',
            'dimensions.width' => 'nullable|numeric|min:0',
            'dimensions.height' => 'nullable|numeric|min:0',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_digital' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'images' => 'nullable|array',
            'images.*.url' => 'required|string',
            'images.*.alt_text' => 'nullable|string',
            'images.*.is_primary' => 'boolean'
        ]);

        // Create slug from name
        $validatedData['slug'] = \Illuminate\Support\Str::slug($validatedData['name']);
        
        // Ensure slug is unique
        $originalSlug = $validatedData['slug'];
        $counter = 1;
        while (Product::where('slug', $validatedData['slug'])->exists()) {
            $validatedData['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $categoryIds = $validatedData['category_ids'] ?? [];
        $images = $validatedData['images'] ?? [];
        unset($validatedData['category_ids'], $validatedData['images']);

        $product = Product::create($validatedData);

        // Attach categories
        if (!empty($categoryIds)) {
            $product->categories()->attach($categoryIds);
        }

        // Create images
        foreach ($images as $index => $imageData) {
            $product->images()->create([
                ...$imageData,
                'sort_order' => $index + 1
            ]);
        }

        $product->load(['categories', 'images']);

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
        if (!$product->is_active) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $product->load(['categories', 'images']);

        return response()->json(['data' => $product]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => ['sometimes', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'track_quantity' => 'boolean',
            'quantity' => 'nullable|integer|min:0',
            'min_quantity' => 'nullable|integer|min:0',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|array',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_digital' => 'boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id'
        ]);

        // Update slug if name changed
        if (isset($validatedData['name']) && $validatedData['name'] !== $product->name) {
            $slug = \Illuminate\Support\Str::slug($validatedData['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validatedData['slug'] = $slug;
        }

        $categoryIds = $validatedData['category_ids'] ?? null;
        unset($validatedData['category_ids']);

        $product->update($validatedData);

        // Update categories if provided
        if ($categoryIds !== null) {
            $product->categories()->sync($categoryIds);
        }

        $product->load(['categories', 'images']);

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
            'quantity' => 'required|integer|min:0',
            'operation' => ['required', Rule::in(['set', 'increment', 'decrement'])]
        ]);

        $operation = $validatedData['operation'];
        $quantity = $validatedData['quantity'];

        switch ($operation) {
            case 'set':
                $product->update(['quantity' => $quantity]);
                break;
            case 'increment':
                $product->incrementStock($quantity);
                break;
            case 'decrement':
                if (!$product->decrementStock($quantity)) {
                    return response()->json(['error' => 'Insufficient stock'], 400);
                }
                break;
        }

        $product->refresh();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'quantity' => $product->quantity,
                'stock_status' => $product->stock_status
            ],
            'message' => 'Stock updated successfully'
        ]);
    }

    /**
     * Get featured products.
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = min($request->get('limit', 10), 20);
        
        $products = Product::with(['categories', 'images'])
                          ->active()
                          ->featured()
                          ->inStock()
                          ->orderBy('created_at', 'desc')
                          ->limit($limit)
                          ->get();

        return response()->json([
            'data' => $products,
            'meta' => ['total' => $products->count()]
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

        $products = Product::with(['categories', 'images'])
                          ->active()
                          ->search($query)
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