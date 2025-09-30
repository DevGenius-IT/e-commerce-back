<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Newsletter extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'name',
        'phone',
        'status',
        'preferences',
        'subscribed_at',
        'unsubscribed_at',
        'subscription_source',
        'unsubscribe_token',
        'bounce_count',
        'last_bounce_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'preferences' => 'array',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_bounce_at' => 'datetime',
        'bounce_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($newsletter) {
            if (!$newsletter->unsubscribe_token) {
                $newsletter->unsubscribe_token = $newsletter->generateUnsubscribeToken();
            }
        });
    }

    /**
     * Get the campaigns this newsletter is associated with.
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'newsletter_campaigns')
                    ->withPivot(['status', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'failed_at', 'bounce_reason', 'failure_reason', 'click_data', 'user_agent', 'ip_address', 'open_count', 'click_count'])
                    ->withTimestamps();
    }

    /**
     * Scope to get subscribed newsletters.
     */
    public function scopeSubscribed($query)
    {
        return $query->where('status', 'subscribed');
    }

    /**
     * Scope to get unsubscribed newsletters.
     */
    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    /**
     * Scope to get pending newsletters.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get bounced newsletters.
     */
    public function scopeBounced($query)
    {
        return $query->where('status', 'bounced');
    }

    /**
     * Scope to filter by subscription source.
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('subscription_source', $source);
    }

    /**
     * Scope to get recently subscribed.
     */
    public function scopeRecentlySubscribed($query, $days = 30)
    {
        return $query->where('subscribed_at', '>=', now()->subDays($days));
    }

    /**
     * Generate a unique unsubscribe token.
     */
    public function generateUnsubscribeToken(): string
    {
        do {
            $token = Str::random(64);
        } while (static::where('unsubscribe_token', $token)->exists());

        return $token;
    }

    /**
     * Subscribe the newsletter.
     */
    public function subscribe(): bool
    {
        $this->status = 'subscribed';
        $this->subscribed_at = now();
        $this->unsubscribed_at = null;
        return $this->save();
    }

    /**
     * Unsubscribe the newsletter.
     */
    public function unsubscribe(): bool
    {
        $this->status = 'unsubscribed';
        $this->unsubscribed_at = now();
        return $this->save();
    }

    /**
     * Mark as bounced.
     */
    public function markAsBounced(string $reason = null): bool
    {
        $this->status = 'bounced';
        $this->bounce_count += 1;
        $this->last_bounce_at = now();
        
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Bounced: " . $reason;
        }

        return $this->save();
    }

    /**
     * Check if newsletter is subscribed.
     */
    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    /**
     * Check if newsletter is unsubscribed.
     */
    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }

    /**
     * Check if newsletter is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if newsletter is bounced.
     */
    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }

    /**
     * Get subscription duration in days.
     */
    public function getSubscriptionDurationAttribute(): ?int
    {
        if (!$this->subscribed_at) {
            return null;
        }

        $endDate = $this->unsubscribed_at ?: now();
        return $this->subscribed_at->diffInDays($endDate);
    }

    /**
     * Get formatted subscription status.
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Get unsubscribe URL.
     */
    public function getUnsubscribeUrlAttribute(): string
    {
        return config('app.url') . '/api/newsletters/unsubscribe/' . $this->unsubscribe_token;
    }

    /**
     * Update preferences.
     */
    public function updatePreferences(array $preferences): bool
    {
        $this->preferences = array_merge($this->preferences ?: [], $preferences);
        return $this->save();
    }

    /**
     * Get preference value.
     */
    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    /**
     * Check if has preference.
     */
    public function hasPreference(string $key): bool
    {
        return array_key_exists($key, $this->preferences ?: []);
    }
}