<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Catalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * The products that belong to the catalog.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_catalogs');
    }
}