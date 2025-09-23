<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vat extends Model
{
    use HasFactory;

    protected $table = 'vat';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'value_',
    ];

    protected $casts = [
        'value_' => 'decimal:2',
    ];
}