<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'ref',
        'price_ht',
        'stock',
        'id_1', // brand_id
    ];

    protected $casts = [
        'price_ht' => 'decimal:2',
        'stock' => 'integer',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the brand that owns the product.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'id_1');
    }

    /**
     * The types that belong to the product.
     */
    public function types(): BelongsToMany
    {
        return $this->belongsToMany(Type::class, 'product_types');
    }

    /**
     * The categories that belong to the product.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * The catalogs that belong to the product.
     */
    public function catalogs(): BelongsToMany
    {
        return $this->belongsToMany(Catalog::class, 'product_catalogs');
    }

    /**
     * Get the attributes for the product.
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class);
    }

    /**
     * Get the characteristics for the product.
     */
    public function characteristics(): HasMany
    {
        return $this->hasMany(Characteristic::class);
    }
}