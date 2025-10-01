<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'question_id',
        'body'
    ];

    protected $dates = ['deleted_at'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
