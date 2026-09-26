<?php

namespace App\Models;

use App\Domain\Businesses\ProfileChangeRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-002-07: a queued name/domain/primary-category change on a claimed
 * Business, awaiting staff approval.
 *
 * @property array<string, array{old: mixed, new: mixed}> $changes
 * @property ProfileChangeRequestStatus $status
 * @property Carbon|null $reviewed_at
 */
class BusinessProfileChangeRequest extends Model
{
    /**
     * Mirrors the DB-level default so a freshly-created instance already
     * reflects it without a round-trip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = ['business_id', 'requested_by', 'changes', 'status'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'status' => ProfileChangeRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
