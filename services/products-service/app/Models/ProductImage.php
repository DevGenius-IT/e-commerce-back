<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'original_url',
        'thumbnail_url',
        'medium_url',
        'filename',
        'type',
        'alt_text',
        'position',
        'size',
        'mime_type',
    ];

    protected $casts = [
        'position' => 'integer',
        'size' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Scope pour images principales
     */
    public function scopeMain($query)
    {
        return $query->where('type', 'main');
    }

    /**
     * Scope pour galerie
     */
    public function scopeGallery($query)
    {
        return $query->where('type', 'gallery');
    }
}
