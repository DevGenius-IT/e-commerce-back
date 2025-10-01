<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BasketItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'basket_id',
        'product_id',
        'quantity',
        'price_ht',
    ];

    protected $casts = [
        'basket_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'price_ht' => 'decimal:2',
    ];

    /**
     * Get the basket that owns the item.
     */
    public function basket()
    {
        return $this->belongsTo(Basket::class);
    }

    /**
     * Get the line total for this item.
     */
    public function getLineTotalAttribute()
    {
        return $this->price_ht * $this->quantity;
    }

    /**
     * Update basket total when item changes.
     */
    protected static function booted()
    {
        static::saved(function ($item) {
            $item->basket->calculateTotal();
        });

        static::deleted(function ($item) {
            $item->basket->calculateTotal();
        });
    }
}