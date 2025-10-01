<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'discount',
        'id_1', // type_id
    ];

    protected $dates = [
        'deleted_at',
    ];

    protected $casts = [
        'discount' => 'decimal:2',
    ];

    /**
     * Get the type that owns the promo code.
     */
    public function type()
    {
        return $this->belongsTo(Type::class, 'id_1');
    }

    /**
     * Get all baskets that use this promo code.
     */
    public function baskets()
    {
        return $this->belongsToMany(Basket::class, 'basket_promo_code')->withTimestamps();
    }
}