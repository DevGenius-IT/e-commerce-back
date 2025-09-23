<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::active()->ordered();

        // Filter by parent
        if ($request->has('parent_id')) {
            $parentId = $request->get('parent_id');
            if ($parentId === 'null' || $parentId === '') {
                $query->roots();
            } else {
                $query->where('parent_id', $parentId);
            }
        }

        // Include products count
        if ($request->get('with_products_count') === 'true') {
            $query->withCount('products');
        }

        // Include hierarchy (children)
        if ($request->get('with_children') === 'true') {
            $query->with(['children' => function ($q) {
                $q->active()->ordered();
            }]);
        }

        // Search by name
        if ($search = $request->get('search')) {
            $query->where('name', 'LIKE', "%{$search}%");
        }

        $categories = $query->get();

        return response()->json([
            'data' => $categories,
            'meta' => ['total' => $categories->count()]
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image_url' => 'nullable|url',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Create slug from name
        $validatedData['slug'] = \Illuminate\Support\Str::slug($validatedData['name']);
        
        // Ensure slug is unique
        $originalSlug = $validatedData['slug'];
        $counter = 1;
        while (Category::where('slug', $validatedData['slug'])->exists()) {
            $validatedData['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Validate parent relationship (prevent circular references)
        if (isset($validatedData['parent_id'])) {
            $parent = Category::find($validatedData['parent_id']);
            if (!$parent || !$parent->is_active) {
                return response()->json(['error' => 'Invalid parent category'], 400);
            }
        }

        $category = Category::create($validatedData);
        $category->load(['parent', 'children']);

        return response()->json([
            'data' => $category,
            'message' => 'Category created successfully'
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $category->load(['parent', 'children' => function ($query) {
            $query->active()->ordered();
        }]);

        return response()->json(['data' => $category]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image_url' => 'nullable|url',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean'
        ]);

        // Update slug if name changed
        if (isset($validatedData['name']) && $validatedData['name'] !== $category->name) {
            $slug = \Illuminate\Support\Str::slug($validatedData['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validatedData['slug'] = $slug;
        }

        // Validate parent relationship (prevent circular references and self-reference)
        if (isset($validatedData['parent_id'])) {
            if ($validatedData['parent_id'] == $category->id) {
                return response()->json(['error' => 'Category cannot be its own parent'], 400);
            }

            $parent = Category::find($validatedData['parent_id']);
            if (!$parent || !$parent->is_active) {
                return response()->json(['error' => 'Invalid parent category'], 400);
            }

            // Check for circular reference
            $current = $parent;
            while ($current && $current->parent_id) {
                if ($current->parent_id == $category->id) {
                    return response()->json(['error' => 'Circular reference detected'], 400);
                }
                $current = $current->parent;
            }
        }

        $category->update($validatedData);
        $category->load(['parent', 'children']);

        return response()->json([
            'data' => $category,
            'message' => 'Category updated successfully'
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'error' => 'Cannot delete category that has products. Please reassign products first.'
            ], 400);
        }

        // Check if category has children
        if ($category->hasChildren()) {
            return response()->json([
                'error' => 'Cannot delete category that has subcategories. Please delete or reassign subcategories first.'
            ], 400);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully']);
    }

    /**
     * Get category tree/hierarchy.
     */
    public function tree(Request $request): JsonResponse
    {
        $categories = Category::active()
                             ->roots()
                             ->with(['descendants' => function ($query) {
                                 $query->active()->ordered();
                             }])
                             ->ordered()
                             ->get();

        return response()->json([
            'data' => $categories,
            'meta' => ['total' => $categories->count()]
        ]);
    }

    /**
     * Get products in category.
     */
    public function products(Request $request, Category $category): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        $query = $category->products()
                         ->with(['categories', 'images'])
                         ->where('is_active', true);

        // Include products from subcategories
        if ($request->get('include_subcategories') === 'true') {
            $subcategoryIds = $category->descendants()->pluck('id')->toArray();
            $subcategoryIds[] = $category->id;
            
            $query = \App\Models\Product::with(['categories', 'images'])
                                       ->whereHas('categories', function ($q) use ($subcategoryIds) {
                                           $q->whereIn('categories.id', $subcategoryIds);
                                       })
                                       ->where('is_active', true);
        }

        // Apply filters
        if ($request->get('in_stock') === 'true') {
            $query->where(function ($q) {
                $q->where('track_quantity', false)
                  ->orWhere('quantity', '>', 0);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        
        if (in_array($sortBy, ['name', 'price', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->get('per_page', 20), 50);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'category' => $category->name,
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'last_page' => $products->lastPage(),
                'has_more' => $products->hasMorePages()
            ]
        ]);
    }

    /**
     * Get category breadcrumb.
     */
    public function breadcrumb(Category $category): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json(['error' => 'Category not found'], 404);
        }

        return response()->json([
            'data' => [
                'path' => $category->path,
                'breadcrumb' => $category->breadcrumb
            ]
        ]);
    }
}