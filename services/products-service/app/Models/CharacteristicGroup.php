<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CharacteristicGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the related characteristics for the characteristic group.
     */
    public function relatedCharacteristics(): HasMany
    {
        return $this->hasMany(RelatedCharacteristic::class, 'id_1');
    }
}