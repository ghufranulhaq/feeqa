<?php

namespace App\Models;

use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\ReasonCode;
use Database\Factories\EnforcementActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * FR-006-14 through FR-006-17: one rung of either ladder ever applied to
 * a Business or a User. Never deleted — lifting a step updates
 * `lifted_by`/`lifted_at`/`lift_reason` rather than removing the row, so
 * the ladder's history stays intact for the Transparency Center (T9).
 *
 * @property EnforcementLadder $ladder
 * @property EnforcementStep $step
 * @property ReasonCode $reason_code
 * @property int $applied_by
 * @property Carbon $applied_at
 * @property Carbon|null $expires_at
 * @property int|null $lifted_by
 * @property Carbon|null $lifted_at
 * @property string|null $lift_reason
 * @property int|null $senior_approved_by
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

    /**
     * Reverses whatever `ApplyEnforcementStep` did to the subject for this
     * row's step. Shared by `LiftEnforcementStep` (a step earned its way
     * back off) and `DecideAppeal` (a step overturned on appeal) so the
     * two callers' own gating (a Senior Moderator + 6-month minimum for
     * lifting a Consumer Warning; a different staff member for deciding
     * an appeal) stays theirs while the mutation itself lives once.
     *
     * `feature_restriction` un-restricting on lift was missing until this
     * method was written for T7 — tasks.md's own T6 description already
     * claimed `LiftEnforcementStep` "un-restricts the two features"
     * generally, not only inside a Consumer Warning, so this corrects the
     * code to match that claim rather than narrowing the claim to match
     * the code.
     *
     * Never restores a suspended `plan` — no billing record of the prior
     * tier exists to restore it to (017 doesn't exist yet), the same
     * honest gap `LiftEnforcementStep` already documented.
     */
    public function reverseSideEffects(): void
    {
        $subject = $this->subject;

        if ($this->step === EnforcementStep::ConsumerWarning && $subject instanceof Business) {
            $subject->update([
                'consumer_warning_at' => null,
                'consumer_warning_reason' => null,
                'restricted_features' => $this->withoutLadderFeatures($subject),
            ]);
        } elseif ($this->step === EnforcementStep::FeatureRestriction && $subject instanceof Business) {
            $subject->update(['restricted_features' => $this->withoutLadderFeatures($subject)]);
        } elseif ($this->step === EnforcementStep::AccountBlock && $subject instanceof User) {
            $subject->update(['blocked_at' => null, 'blocked_reason' => null]);
        }
    }

    /**
     * @return list<string>|null
     */
    private function withoutLadderFeatures(Business $business): ?array
    {
        $restricted = array_values(array_diff(
            $business->restricted_features ?? [],
            [RestrictableFeature::Invitations->value, RestrictableFeature::ProfileEdits->value],
        ));

        return $restricted ?: null;
    }
}
