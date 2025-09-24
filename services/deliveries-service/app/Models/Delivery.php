<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Delivery extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tracking_number',
        'order_id',
        'sale_point_id',
        'status_id',
        'delivery_method',
        'shipping_cost',
        'delivery_address',
        'special_instructions',
        'estimated_delivery_date',
        'actual_delivery_date',
        'shipped_at',
        'carrier_name',
        'carrier_tracking_number',
        'carrier_details',
        'recipient_name',
        'recipient_phone',
        'delivery_notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'shipping_cost' => 'decimal:2',
        'carrier_details' => 'array', // Store as JSON
        'estimated_delivery_date' => 'datetime',
        'actual_delivery_date' => 'datetime',
        'shipped_at' => 'datetime',
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
        
        static::creating(function ($delivery) {
            if (!$delivery->tracking_number) {
                $delivery->tracking_number = $delivery->generateTrackingNumber();
            }
        });
    }

    /**
     * Get the sale point that owns this delivery.
     */
    public function salePoint(): BelongsTo
    {
        return $this->belongsTo(SalePoint::class);
    }

    /**
     * Get the status of this delivery.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Scope to filter by delivery method.
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('delivery_method', $method);
    }

    /**
     * Scope to filter by carrier.
     */
    public function scopeByCarrier($query, $carrier)
    {
        return $query->where('carrier_name', $carrier);
    }

    /**
     * Scope to get shipped deliveries.
     */
    public function scopeShipped($query)
    {
        return $query->whereNotNull('shipped_at');
    }

    /**
     * Scope to get delivered deliveries.
     */
    public function scopeDelivered($query)
    {
        return $query->whereNotNull('actual_delivery_date');
    }

    /**
     * Scope to get pending deliveries.
     */
    public function scopePending($query)
    {
        return $query->whereNull('shipped_at');
    }

    /**
     * Scope to get overdue deliveries.
     */
    public function scopeOverdue($query)
    {
        return $query->where('estimated_delivery_date', '<', now())
                    ->whereNull('actual_delivery_date');
    }

    /**
     * Generate a unique tracking number.
     */
    public function generateTrackingNumber(): string
    {
        do {
            $trackingNumber = 'DEL' . date('Ymd') . strtoupper(substr(uniqid(), -6));
        } while (static::where('tracking_number', $trackingNumber)->exists());

        return $trackingNumber;
    }

    /**
     * Check if delivery is shipped.
     */
    public function isShipped(): bool
    {
        return !is_null($this->shipped_at);
    }

    /**
     * Check if delivery is delivered.
     */
    public function isDelivered(): bool
    {
        return !is_null($this->actual_delivery_date);
    }

    /**
     * Check if delivery is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->estimated_delivery_date && 
               $this->estimated_delivery_date->isPast() && 
               !$this->isDelivered();
    }

    /**
     * Get estimated delivery days from now.
     */
    public function getEstimatedDaysAttribute(): ?int
    {
        if (!$this->estimated_delivery_date) {
            return null;
        }

        return now()->diffInDays($this->estimated_delivery_date, false);
    }

    /**
     * Get delivery duration in days.
     */
    public function getDeliveryDurationAttribute(): ?int
    {
        if (!$this->shipped_at || !$this->actual_delivery_date) {
            return null;
        }

        return $this->shipped_at->diffInDays($this->actual_delivery_date);
    }

    /**
     * Get formatted tracking display.
     */
    public function getTrackingDisplayAttribute(): string
    {
        if ($this->carrier_tracking_number) {
            return "{$this->tracking_number} ({$this->carrier_name}: {$this->carrier_tracking_number})";
        }

        return $this->tracking_number;
    }

    /**
     * Update delivery status.
     */
    public function updateStatus($statusId, $notes = null): bool
    {
        $this->status_id = $statusId;
        
        if ($notes) {
            $this->delivery_notes = $notes;
        }

        // Auto-update timestamps based on status
        $status = Status::find($statusId);
        if ($status) {
            switch (strtolower($status->name)) {
                case 'shipped':
                case 'in_transit':
                    if (!$this->shipped_at) {
                        $this->shipped_at = now();
                    }
                    break;
                case 'delivered':
                    if (!$this->actual_delivery_date) {
                        $this->actual_delivery_date = now();
                    }
                    break;
            }
        }

        return $this->save();
    }
}