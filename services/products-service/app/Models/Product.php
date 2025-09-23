<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'sku',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost',
        'track_quantity',
        'quantity',
        'min_quantity',
        'weight',
        'dimensions',
        'meta_title',
        'meta_description',
        'tags',
        'is_active',
        'is_featured',
        'requires_shipping',
        'is_digital'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'dimensions' => 'json',
        'tags' => 'json',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'requires_shipping' => 'boolean',
        'is_digital' => 'boolean',
        'track_quantity' => 'boolean',
        'quantity' => 'integer',
        'min_quantity' => 'integer'
    ];

    /**
     * Get the categories for this product.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories')
                    ->withTimestamps();
    }

    /**
     * Get the images for this product.
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the primary image for this product.
     */
    public function primaryImage(): HasMany
    {
        return $this->images()->where('is_primary', true)->limit(1);
    }

    /**
     * Get the formatted price.
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Get the formatted compare price.
     */
    public function getFormattedComparePriceAttribute(): ?string
    {
        return $this->compare_price ? '$' . number_format($this->compare_price, 2) : null;
    }

    /**
     * Get the discount percentage.
     */
    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->compare_price || $this->compare_price <= $this->price) {
            return null;
        }
        
        return (int) round((($this->compare_price - $this->price) / $this->compare_price) * 100);
    }

    /**
     * Check if product is on sale.
     */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_price && $this->compare_price > $this->price;
    }

    /**
     * Check if product is in stock.
     */
    public function getIsInStockAttribute(): bool
    {
        if (!$this->track_quantity) {
            return true;
        }
        
        return $this->quantity > 0;
    }

    /**
     * Check if product is low stock.
     */
    public function getIsLowStockAttribute(): bool
    {
        if (!$this->track_quantity) {
            return false;
        }
        
        return $this->quantity <= $this->min_quantity && $this->quantity > 0;
    }

    /**
     * Check if product is out of stock.
     */
    public function getIsOutOfStockAttribute(): bool
    {
        if (!$this->track_quantity) {
            return false;
        }
        
        return $this->quantity <= 0;
    }

    /**
     * Get stock status string.
     */
    public function getStockStatusAttribute(): string
    {
        if (!$this->track_quantity) {
            return 'unlimited';
        }
        
        if ($this->is_out_of_stock) {
            return 'out_of_stock';
        }
        
        if ($this->is_low_stock) {
            return 'low_stock';
        }
        
        return 'in_stock';
    }

    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured products.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include in-stock products.
     */
    public function scopeInStock($query)
    {
        return $query->where(function ($q) {
            $q->where('track_quantity', false)
              ->orWhere('quantity', '>', 0);
        });
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function ($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
    }

    /**
     * Scope a query to filter by price range.
     */
    public function scopePriceRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('price', '>=', $min);
        }
        
        if ($max !== null) {
            $query->where('price', '<=', $max);
        }
        
        return $query;
    }

    /**
     * Scope a query to search products by name, description, or SKU.
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhere('sku', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Update inventory when product is purchased.
     */
    public function decrementStock(int $quantity): bool
    {
        if (!$this->track_quantity) {
            return true;
        }
        
        if ($this->quantity < $quantity) {
            return false;
        }
        
        $this->decrement('quantity', $quantity);
        return true;
    }

    /**
     * Restock product.
     */
    public function incrementStock(int $quantity): void
    {
        if ($this->track_quantity) {
            $this->increment('quantity', $quantity);
        }
    }
}