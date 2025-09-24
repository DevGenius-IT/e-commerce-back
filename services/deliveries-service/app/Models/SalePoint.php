<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalePoint extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'postal_code',
        'phone',
        'email',
        'opening_hours',
        'is_active',
        'latitude',
        'longitude',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'opening_hours' => 'array', // Store as JSON
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the deliveries for this sale point.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * Scope to get only active sale points.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('deleted_at');
    }

    /**
     * Scope to search by city.
     */
    public function scopeInCity($query, $city)
    {
        return $query->where('city', 'like', "%{$city}%");
    }

    /**
     * Scope to search by postal code.
     */
    public function scopeInPostalCode($query, $postalCode)
    {
        return $query->where('postal_code', 'like', "%{$postalCode}%");
    }

    /**
     * Get deliveries count for this sale point.
     */
    public function getDeliveriesCountAttribute(): int
    {
        return $this->deliveries()->count();
    }

    /**
     * Get active deliveries count.
     */
    public function getActiveDeliveriesCountAttribute(): int
    {
        return $this->deliveries()
            ->whereHas('status', function ($query) {
                $query->whereNotIn('name', ['delivered', 'cancelled', 'returned']);
            })->count();
    }

    /**
     * Get full address as a single string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->postal_code,
            $this->city,
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Check if sale point has geographical coordinates.
     */
    public function hasCoordinates(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Calculate distance to given coordinates (in kilometers).
     */
    public function distanceTo($latitude, $longitude): ?float
    {
        if (!$this->hasCoordinates()) {
            return null;
        }

        $earthRadius = 6371; // Earth radius in kilometers

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}