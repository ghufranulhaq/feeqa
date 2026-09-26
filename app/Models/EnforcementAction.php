<?php

namespace App\Models;

use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\ReasonCode;
use Database\Factories\EnforcementActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FR-006-14 through FR-006-17: one rung of either ladder ever applied to
 * a Business or a User. Never deleted — lifting a step updates
 * `lifted_by`/`lifted_at`/`lift_reason` rather than removing the row, so
 * the ladder's history stays intact for the Transparency Center (T9).
 *
 * @property EnforcementLadder $ladder
 * @property EnforcementStep $step
 * @property ReasonCode $reason_code
 */
class EnforcementAction extends Model
{
    /** @use HasFactory<EnforcementActionFactory> */
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'ladder',
        'step',
        'reason_code',
        'applied_by',
        'applied_at',
        'expires_at',
        'lifted_by',
        'lifted_at',
        'lift_reason',
        'senior_approved_by',
    ];

    protected function casts(): array
    {
        return [
            'ladder' => EnforcementLadder::class,
            'step' => EnforcementStep::class,
            'reason_code' => ReasonCode::class,
            'applied_at' => 'datetime',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function liftedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function isLifted(): bool
    {
        return $this->lifted_at !== null;
    }
}
