<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'url',
        'alt_text',
        'title',
        'is_primary',
        'sort_order'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer'
    ];

    /**
     * Get the product that owns the image.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the full URL for the image.
     */
    public function getFullUrlAttribute(): string
    {
        // If URL is already absolute, return as is
        if (filter_var($this->url, FILTER_VALIDATE_URL)) {
            return $this->url;
        }
        
        // Otherwise, prepend the storage URL
        return config('app.url') . '/storage/' . ltrim($this->url, '/');
    }

    /**
     * Get thumbnail URL (for future implementation with image processing).
     */
    public function getThumbnailUrlAttribute(): string
    {
        // For now, return the same URL. In the future, this could generate thumbnails
        return $this->full_url;
    }

    /**
     * Scope a query to only include primary images.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Set this image as the primary image for the product.
     */
    public function makePrimary(): void
    {
        // Remove primary status from all other images of this product
        static::where('product_id', $this->product_id)
              ->where('id', '!=', $this->id)
              ->update(['is_primary' => false]);
        
        // Set this image as primary
        $this->update(['is_primary' => true]);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a new image, if no primary image exists for the product,
        // make this the primary image
        static::creating(function ($image) {
            if (!$image->product->images()->where('is_primary', true)->exists()) {
                $image->is_primary = true;
            }
            
            // Set sort order if not provided
            if (is_null($image->sort_order)) {
                $maxOrder = $image->product->images()->max('sort_order') ?? 0;
                $image->sort_order = $maxOrder + 1;
            }
        });

        // When deleting a primary image, make the next image primary
        static::deleting(function ($image) {
            if ($image->is_primary) {
                $nextImage = $image->product->images()
                                  ->where('id', '!=', $image->id)
                                  ->orderBy('sort_order')
                                  ->first();
                
                if ($nextImage) {
                    $nextImage->update(['is_primary' => true]);
                }
            }
        });
    }
}