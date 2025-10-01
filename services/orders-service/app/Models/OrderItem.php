<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price_ht',
        'unit_price_ttc',
        'total_price_ht',
        'total_price_ttc',
        'vat_rate',
        'product_name',
        'product_ref',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'unit_price_ht' => 'decimal:2',
        'unit_price_ttc' => 'decimal:2',
        'total_price_ht' => 'decimal:2',
        'total_price_ttc' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($orderItem) {
            // Calculate totals when saving
            $orderItem->total_price_ht = $orderItem->unit_price_ht * $orderItem->quantity;
            $orderItem->total_price_ttc = $orderItem->unit_price_ttc * $orderItem->quantity;
        });

        static::saved(function ($orderItem) {
            // Update parent order totals
            if ($orderItem->order) {
                $orderItem->order->calculateTotals();
            }
        });

        static::deleted(function ($orderItem) {
            // Update parent order totals
            if ($orderItem->order) {
                $orderItem->order->calculateTotals();
            }
        });
    }

    /**
     * Get the order that owns the order item.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Calculate the VAT amount for this item.
     */
    public function getVatAmountAttribute(): float
    {
        return $this->total_price_ttc - $this->total_price_ht;
    }

    /**
     * Get the discount amount for this item.
     */
    public function getDiscountAmountAttribute(): float
    {
        return $this->unit_price_ht * $this->quantity - $this->total_price_ht;
    }
}