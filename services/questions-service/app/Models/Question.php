<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'body'
    ];

    protected $dates = ['deleted_at'];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
