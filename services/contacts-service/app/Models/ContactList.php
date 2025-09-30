<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'type',
        'status',
        'criteria',
        'is_dynamic',
        'contact_count',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'criteria' => 'array',
        'is_dynamic' => 'boolean',
        'contact_count' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope for active lists
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for dynamic lists
     */
    public function scopeDynamic($query)
    {
        return $query->where('is_dynamic', true);
    }

    /**
     * Scope for static lists
     */
    public function scopeStatic($query)
    {
        return $query->where('is_dynamic', false);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
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
     * Relationship with contacts
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_contacts')
                    ->withPivot(['status', 'added_at', 'removed_at', 'added_by'])
                    ->withTimestamps();
    }

    /**
     * Get only active contacts in this list
     */
    public function activeContacts(): BelongsToMany
    {
        return $this->contacts()
                    ->wherePivot('status', 'active')
                    ->where('contacts.status', 'active');
    }

    /**
     * Get subscribed contacts in this list
     */
    public function subscribedContacts(): BelongsToMany
    {
        return $this->activeContacts()
                    ->where('newsletter_subscribed', true);
    }

    /**
     * Add contact to list
     */
    public function addContact(Contact $contact, ?int $addedBy = null): self
    {
        $this->contacts()->syncWithoutDetaching([
            $contact->id => [
                'status' => 'active',
                'added_at' => now(),
                'added_by' => $addedBy,
            ]
        ]);

        $this->refreshContactCount();

        return $this;
    }

    /**
     * Remove contact from list
     */
    public function removeContact(Contact $contact): self
    {
        $this->contacts()->updateExistingPivot($contact->id, [
            'status' => 'inactive',
            'removed_at' => now(),
        ]);

        $this->refreshContactCount();

        return $this;
    }

    /**
     * Add multiple contacts to list
     */
    public function addContacts(array $contactIds, ?int $addedBy = null): self
    {
        $syncData = [];
        foreach ($contactIds as $contactId) {
            $syncData[$contactId] = [
                'status' => 'active',
                'added_at' => now(),
                'added_by' => $addedBy,
            ];
        }

        $this->contacts()->syncWithoutDetaching($syncData);
        $this->refreshContactCount();

        return $this;
    }

    /**
     * Refresh contact count
     */
    public function refreshContactCount(): self
    {
        $count = $this->activeContacts()->count();
        $this->update(['contact_count' => $count]);

        return $this;
    }

    /**
     * Apply dynamic criteria to get matching contacts
     */
    public function applyDynamicCriteria()
    {
        if (!$this->is_dynamic || empty($this->criteria)) {
            return collect();
        }

        $query = Contact::query();

        foreach ($this->criteria as $criterion) {
            $field = $criterion['field'] ?? null;
            $operator = $criterion['operator'] ?? '=';
            $value = $criterion['value'] ?? null;
            $logic = $criterion['logic'] ?? 'and'; // and/or

            if (!$field || $value === null) {
                continue;
            }

            if ($logic === 'and') {
                $query->where($field, $operator, $value);
            } else {
                $query->orWhere($field, $operator, $value);
            }
        }

        return $query->get();
    }

    /**
     * Sync dynamic list (refresh contacts based on criteria)
     */
    public function syncDynamicContacts(?int $syncedBy = null): self
    {
        if (!$this->is_dynamic) {
            return $this;
        }

        $matchingContacts = $this->applyDynamicCriteria();
        
        // Remove all current contacts
        $this->contacts()->updateExistingPivot(
            $this->contacts()->pluck('contact_id')->toArray(),
            ['status' => 'inactive', 'removed_at' => now()]
        );

        // Add matching contacts
        if ($matchingContacts->isNotEmpty()) {
            $this->addContacts($matchingContacts->pluck('id')->toArray(), $syncedBy);
        }

        return $this;
    }

    /**
     * Get list statistics
     */
    public function getStats(): array
    {
        $activeContacts = $this->activeContacts();
        
        return [
            'total_contacts' => $activeContacts->count(),
            'subscribed_contacts' => $activeContacts->where('newsletter_subscribed', true)->count(),
            'marketing_subscribed' => $activeContacts->where('marketing_subscribed', true)->count(),
            'unsubscribed_contacts' => $activeContacts->where('newsletter_subscribed', false)->count(),
            'bounced_contacts' => $activeContacts->where('status', 'bounced')->count(),
            'last_updated' => $this->updated_at,
        ];
    }
}