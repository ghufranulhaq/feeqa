<?php

namespace App\Models;

use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use Database\Factories\FlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FR-006-07: one notice against a piece of content, filed by a signed-in
 * reporter, a guest (email required), or a business (`is_business_flag`).
 *
 * @property ReasonCode $reason_code
 * @property FlagStatus $status
 * @property array<int, string>|null $evidence_paths
 */
class Flag extends Model
{
    /** @use HasFactory<FlagFactory> */
    use HasFactory;

    protected $fillable = [
        'flaggable_type',
        'flaggable_id',
        'reporter_id',
        'reporter_email',
        'reason_code',
        'details',
        'evidence_paths',
        'status',
        'is_business_flag',
        'business_id',
        'assigned_to',
        'decided_by',
        'decided_at',
        'decision_reason',
        'sla_due_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_code' => ReasonCode::class,
            'evidence_paths' => 'array',
            'status' => FlagStatus::class,
            'is_business_flag' => 'boolean',
            'decided_at' => 'datetime',
            'sla_due_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function flaggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * FR-006-09: the business that filed this flag, when `is_business_flag`.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isUnresolved(): bool
    {
        return in_array($this->status, [FlagStatus::Open, FlagStatus::Blurred], true);
    }
}
