<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Characteristic extends Model
{
    use HasFactory;

    protected $fillable = [
        'value_',
        'product_id',
        'related_characteristic_id',
    ];

    /**
     * Get the product that owns the characteristic.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the related characteristic that owns the characteristic.
     */
    public function relatedCharacteristic(): BelongsTo
    {
        return $this->belongsTo(RelatedCharacteristic::class);
    }
}