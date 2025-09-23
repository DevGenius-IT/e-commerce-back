<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelatedCharacteristic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'id_1',
    ];

    /**
     * Get the characteristic group that owns the related characteristic.
     */
    public function characteristicGroup(): BelongsTo
    {
        return $this->belongsTo(CharacteristicGroup::class, 'id_1');
    }

    /**
     * Get the characteristics for the related characteristic.
     */
    public function characteristics(): HasMany
    {
        return $this->hasMany(Characteristic::class);
    }
}