<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Type extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'symbol',
    ];

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Get all promo codes for this type.
     */
    public function promoCodes()
    {
        return $this->hasMany(PromoCode::class, 'id_1');
    }
}