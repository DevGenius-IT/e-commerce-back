<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'company',
        'phone',
        'status',
        'source',
        'language',
        'country',
        'city',
        'birth_date',
        'gender',
        'newsletter_subscribed',
        'marketing_subscribed',
        'sms_subscribed',
        'subscribed_at',
        'unsubscribed_at',
        'last_email_sent_at',
        'last_email_opened_at',
        'last_email_clicked_at',
        'email_open_count',
        'email_click_count',
        'user_id',
        'custom_fields',
        'tags',
        'notes',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_email_sent_at' => 'datetime',
        'last_email_opened_at' => 'datetime',
        'last_email_clicked_at' => 'datetime',
        'newsletter_subscribed' => 'boolean',
        'marketing_subscribed' => 'boolean',
        'sms_subscribed' => 'boolean',
        'email_open_count' => 'integer',
        'email_click_count' => 'integer',
        'custom_fields' => 'array',
        'tags' => 'array',
    ];

    /**
     * Get the full name of the contact
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get display name (full name or email if no name)
     */
    public function getDisplayNameAttribute(): string
    {
        $fullName = $this->getFullNameAttribute();
        return empty($fullName) ? $this->email : $fullName;
    }

    /**
     * Scope for active contacts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for subscribed contacts
     */
    public function scopeSubscribed($query)
    {
        return $query->where('newsletter_subscribed', true);
    }

    /**
     * Scope for marketing subscribed contacts
     */
    public function scopeMarketingSubscribed($query)
    {
        return $query->where('marketing_subscribed', true);
    }

    /**
     * Search scope
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('email', 'like', "%{$term}%")
                  ->orWhere('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('company', 'like', "%{$term}%");
        });
    }

    /**
     * Relationship with contact lists
     */
    public function contactLists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_contacts')
                    ->withPivot(['status', 'added_at', 'removed_at', 'added_by'])
                    ->withTimestamps();
    }

    /**
     * Get only active contact lists
     */
    public function activeContactLists(): BelongsToMany
    {
        return $this->contactLists()->wherePivot('status', 'active');
    }

    /**
     * Subscribe to newsletter
     */
    public function subscribeToNewsletter(): self
    {
        $this->update([
            'newsletter_subscribed' => true,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
            'status' => 'active'
        ]);

        return $this;
    }

    /**
     * Unsubscribe from newsletter
     */
    public function unsubscribeFromNewsletter(): self
    {
        $this->update([
            'newsletter_subscribed' => false,
            'marketing_subscribed' => false,
            'unsubscribed_at' => now(),
        ]);

        return $this;
    }

    /**
     * Subscribe to marketing
     */
    public function subscribeToMarketing(): self
    {
        $this->update([
            'marketing_subscribed' => true,
            'subscribed_at' => $this->subscribed_at ?? now(),
            'unsubscribed_at' => null,
            'status' => 'active'
        ]);

        return $this;
    }

    /**
     * Record email sent
     */
    public function recordEmailSent(): self
    {
        $this->update([
            'last_email_sent_at' => now(),
        ]);

        return $this;
    }

    /**
     * Record email opened
     */
    public function recordEmailOpened(): self
    {
        $this->increment('email_open_count');
        $this->update([
            'last_email_opened_at' => now(),
        ]);

        return $this;
    }

    /**
     * Record email clicked
     */
    public function recordEmailClicked(): self
    {
        $this->increment('email_click_count');
        $this->update([
            'last_email_clicked_at' => now(),
        ]);

        return $this;
    }

    /**
     * Add tag to contact
     */
    public function addTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }

        return $this;
    }

    /**
     * Remove tag from contact
     */
    public function removeTag(string $tag): self
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);
        $this->update(['tags' => array_values($tags)]);

        return $this;
    }

    /**
     * Check if contact has tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }
}