<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'description',
        'type',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
    ];

    /**
     * Scope for custom tags
     */
    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    /**
     * Scope for system tags
     */
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    /**
     * Search scope
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Increment usage count
     */
    public function incrementUsage(): self
    {
        $this->increment('usage_count');
        return $this;
    }

    /**
     * Decrement usage count
     */
    public function decrementUsage(): self
    {
        $this->decrement('usage_count');
        return $this;
    }

    /**
     * Get contacts that have this tag
     */
    public function getContactsWithTag()
    {
        return Contact::whereJsonContains('tags', $this->name)->get();
    }

    /**
     * Get popular tags (most used)
     */
    public static function popular(int $limit = 10)
    {
        return static::orderBy('usage_count', 'desc')->limit($limit)->get();
    }

    /**
     * Create or get tag by name
     */
    public static function findOrCreate(string $name, array $attributes = []): self
    {
        $tag = static::where('name', $name)->first();

        if (!$tag) {
            $tag = static::create(array_merge([
                'name' => $name,
                'type' => 'custom',
            ], $attributes));
        }

        return $tag;
    }
}