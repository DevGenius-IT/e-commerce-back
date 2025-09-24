<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_number',
        'total_amount_ht',
        'total_amount_ttc',
        'total_discount',
        'vat_amount',
        'notes',
        'user_id',
        'billing_address_id',
        'shipping_address_id',
        'status_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_amount_ht' => 'decimal:2',
        'total_amount_ttc' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (empty($order->order_number)) {
                $order->order_number = self::generateOrderNumber();
            }
        });

        static::saved(function ($order) {
            // Recalculate totals when order is saved
            $order->calculateTotals();
        });
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('Ymd');
        $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $orderNumber = $prefix . '-' . $timestamp . '-' . $random;
        
        // Ensure uniqueness
        while (self::where('order_number', $orderNumber)->exists()) {
            $random = str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            $orderNumber = $prefix . '-' . $timestamp . '-' . $random;
        }
        
        return $orderNumber;
    }

    /**
     * Calculate and update order totals.
     *
     * @return void
     */
    public function calculateTotals(): void
    {
        $items = $this->orderItems;
        
        $totalHt = $items->sum('total_price_ht');
        $totalTtc = $items->sum('total_price_ttc');
        $vatAmount = $totalTtc - $totalHt;

        // Update without triggering events to avoid recursion
        $this->updateQuietly([
            'total_amount_ht' => $totalHt - $this->total_discount,
            'total_amount_ttc' => $totalTtc - $this->total_discount,
            'vat_amount' => $vatAmount,
        ]);
    }

    /**
     * Get the order status.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status_id');
    }

    /**
     * Get the order items.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the order items with their product information.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Scope to get orders for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get orders by status.
     */
    public function scopeByStatus($query, $status)
    {
        if (is_string($status)) {
            return $query->whereHas('status', function ($q) use ($status) {
                $q->where('name', $status);
            });
        }
        
        return $query->where('status_id', $status);
    }

    /**
     * Check if the order can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status->name, ['pending', 'confirmed']);
    }

    /**
     * Check if the order is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status->name === 'delivered';
    }
}