<?php

namespace App\Models;

use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Verification\TransactionRecordHash;
use Database\Factories\ReviewInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-005-01 through FR-005-12: one invitation, whichever method produced
 * it. `recipient_email`/`reference` are encrypted at rest; the paired
 * `_hash` columns are keyed HMACs used for every lookup an encrypted
 * column can't support (recipient/reference limits, suppression, API
 * replay).
 *
 * @property InvitationMethod $method
 * @property InvitationStatus $status
 * @property Carbon $scheduled_at
 * @property Carbon $expires_at
 * @property array<int, string>|null $product_skus
 */
class ReviewInvitation extends Model
{
    /** @use HasFactory<ReviewInvitationFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'queued',
    ];

    protected $fillable = [
        'business_id',
        'location_id',
        'template_id',
        'created_by',
        'review_id',
        'method',
        'status',
        'recipient_email',
        'recipient_name',
        'locale',
        'reference',
        'product_skus',
        'token',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'reminder_sent_at',
        'clicked_at',
        'reviewed_at',
        'bounced_at',
        'complained_at',
        'cancelled_at',
        'cancellation_reason',
        'queued_reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => InvitationMethod::class,
            'status' => InvitationStatus::class,
            'recipient_email' => 'encrypted',
            'reference' => 'encrypted',
            'product_skus' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'clicked_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * `recipient_email_hash`/`reference_hash` are always derived here, not
     * mass-assignable, so they can never drift out of sync with the
     * encrypted `recipient_email`/`reference` they're a lookup key for.
     */
    protected static function booted(): void
    {
        static::saving(function (self $invitation): void {
            if ($invitation->isDirty('recipient_email')) {
                $invitation->recipient_email_hash = $invitation->recipient_email === null
                    ? null
                    : TransactionRecordHash::email($invitation->recipient_email);
            }

            if ($invitation->isDirty('reference')) {
                $invitation->reference_hash = $invitation->reference === null
                    ? null
                    : TransactionRecordHash::reference($invitation->reference);
            }
        });
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<InvitationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvitationTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
