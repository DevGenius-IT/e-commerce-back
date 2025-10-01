<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Basket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'amount',
        'user_id',
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'user_id' => 'integer',
    ];

    /**
     * Get all items for this basket.
     */
    public function items()
    {
        return $this->hasMany(BasketItem::class);
    }

    /**
     * Get all promo codes applied to this basket.
     */
    public function promoCodes()
    {
        return $this->belongsToMany(PromoCode::class, 'basket_promo_code')->withTimestamps();
    }

    /**
     * Calculate the total amount including discounts.
     */
    public function calculateTotal()
    {
        $subtotal = $this->items->sum(function ($item) {
            return $item->price_ht * $item->quantity;
        });

        $discount = $this->promoCodes->sum('discount');

        $total = max(0, $subtotal - $discount);
        
        $this->update(['amount' => $total]);
        
        return $total;
    }

    /**
     * Get the subtotal before discounts.
     */
    public function getSubtotalAttribute()
    {
        return $this->items->sum(function ($item) {
            return $item->price_ht * $item->quantity;
        });
    }

    /**
     * Get the total discount amount.
     */
    public function getTotalDiscountAttribute()
    {
        return $this->promoCodes->sum('discount');
    }
}