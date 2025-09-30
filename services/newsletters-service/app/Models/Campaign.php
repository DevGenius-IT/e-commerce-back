<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Campaign extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'subject',
        'content',
        'plain_text',
        'status',
        'scheduled_at',
        'sent_at',
        'created_by',
        'targeting_criteria',
        'campaign_type',
        'total_recipients',
        'total_sent',
        'total_delivered',
        'total_opened',
        'total_clicked',
        'total_bounced',
        'total_unsubscribed',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'targeting_criteria' => 'array',
        'total_recipients' => 'integer',
        'total_sent' => 'integer',
        'total_delivered' => 'integer',
        'total_opened' => 'integer',
        'total_clicked' => 'integer',
        'total_bounced' => 'integer',
        'total_unsubscribed' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the newsletters this campaign is associated with.
     */
    public function newsletters(): BelongsToMany
    {
        return $this->belongsToMany(Newsletter::class, 'newsletter_campaigns')
                    ->withPivot(['status', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'bounced_at', 'failed_at', 'bounce_reason', 'failure_reason', 'click_data', 'user_agent', 'ip_address', 'open_count', 'click_count'])
                    ->withTimestamps();
    }

    /**
     * Get the email template associated with this campaign.
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    /**
     * Scope to get draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to get scheduled campaigns.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope to get sent campaigns.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get sending campaigns.
     */
    public function scopeSending($query)
    {
        return $query->where('status', 'sending');
    }

    /**
     * Scope to get campaigns ready to send.
     */
    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope to filter by campaign type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('campaign_type', $type);
    }

    /**
     * Scope to get recent campaigns.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Mark campaign as sending.
     */
    public function markAsSending(): bool
    {
        $this->status = 'sending';
        return $this->save();
    }

    /**
     * Mark campaign as sent.
     */
    public function markAsSent(): bool
    {
        $this->status = 'sent';
        $this->sent_at = now();
        return $this->save();
    }

    /**
     * Mark campaign as failed.
     */
    public function markAsFailed(): bool
    {
        $this->status = 'failed';
        return $this->save();
    }

    /**
     * Schedule the campaign.
     */
    public function schedule(Carbon $scheduledAt): bool
    {
        $this->status = 'scheduled';
        $this->scheduled_at = $scheduledAt;
        return $this->save();
    }

    /**
     * Cancel the campaign.
     */
    public function cancel(): bool
    {
        if ($this->status !== 'sending') {
            $this->status = 'cancelled';
            return $this->save();
        }
        return false;
    }

    /**
     * Check if campaign is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if campaign is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Check if campaign is sending.
     */
    public function isSending(): bool
    {
        return $this->status === 'sending';
    }

    /**
     * Check if campaign is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if campaign is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Check if campaign is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get open rate percentage.
     */
    public function getOpenRateAttribute(): float
    {
        if ($this->total_delivered == 0) {
            return 0;
        }
        return round(($this->total_opened / $this->total_delivered) * 100, 2);
    }

    /**
     * Get click rate percentage.
     */
    public function getClickRateAttribute(): float
    {
        if ($this->total_delivered == 0) {
            return 0;
        }
        return round(($this->total_clicked / $this->total_delivered) * 100, 2);
    }

    /**
     * Get bounce rate percentage.
     */
    public function getBounceRateAttribute(): float
    {
        if ($this->total_sent == 0) {
            return 0;
        }
        return round(($this->total_bounced / $this->total_sent) * 100, 2);
    }

    /**
     * Get delivery rate percentage.
     */
    public function getDeliveryRateAttribute(): float
    {
        if ($this->total_sent == 0) {
            return 0;
        }
        return round(($this->total_delivered / $this->total_sent) * 100, 2);
    }

    /**
     * Get unsubscribe rate percentage.
     */
    public function getUnsubscribeRateAttribute(): float
    {
        if ($this->total_delivered == 0) {
            return 0;
        }
        return round(($this->total_unsubscribed / $this->total_delivered) * 100, 2);
    }

    /**
     * Get campaign performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'total_recipients' => $this->total_recipients,
            'total_sent' => $this->total_sent,
            'total_delivered' => $this->total_delivered,
            'total_opened' => $this->total_opened,
            'total_clicked' => $this->total_clicked,
            'total_bounced' => $this->total_bounced,
            'total_unsubscribed' => $this->total_unsubscribed,
            'open_rate' => $this->open_rate,
            'click_rate' => $this->click_rate,
            'bounce_rate' => $this->bounce_rate,
            'delivery_rate' => $this->delivery_rate,
            'unsubscribe_rate' => $this->unsubscribe_rate,
        ];
    }

    /**
     * Update campaign statistics.
     */
    public function updateStatistics(): bool
    {
        $this->total_sent = $this->newsletters()->wherePivot('status', '!=', 'pending')->count();
        $this->total_delivered = $this->newsletters()->wherePivot('status', 'delivered')->orWherePivot('status', 'opened')->orWherePivot('status', 'clicked')->count();
        $this->total_opened = $this->newsletters()->wherePivotNotNull('opened_at')->count();
        $this->total_clicked = $this->newsletters()->wherePivotNotNull('clicked_at')->count();
        $this->total_bounced = $this->newsletters()->wherePivot('status', 'bounced')->count();

        return $this->save();
    }
}