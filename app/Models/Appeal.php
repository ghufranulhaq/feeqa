<?php

namespace App\Models;

use App\Domain\Moderation\AppealStatus;
use Database\Factories\AppealFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FR-006-18, FR-006-19: one appeal against one decision by one affected
 * party. `appealable` covers `EnforcementAction`, `Flag`, and `Review` —
 * the "enforcement_action or moderation decision" tasks.md T7 names.
 *
 * @property AppealStatus $status
 * @property array<int, string>|null $evidence_paths
 */
class Appeal extends Model
{
    /** @use HasFactory<AppealFactory> */
    use HasFactory;

    protected $fillable = [
        'appealable_type',
        'appealable_id',
        'appellant_id',
        'statement',
        'evidence_paths',
        'status',
        'decided_by',
        'decided_at',
        'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'status' => AppealStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function appealable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appellant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appellant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === AppealStatus::Pending;
    }
}
