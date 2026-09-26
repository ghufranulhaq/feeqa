<?php

namespace App\Models;

use Database\Factories\AuditSampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-006-20: one automated `Screening` decision pulled into the weekly
 * audit sample, awaiting (or holding) a staff correct/incorrect verdict.
 * `ComputeRulePrecision` reads decided rows to score each rule's
 * precision and disable one that falls below the 99% bar.
 */
class AuditSample extends Model
{
    /** @use HasFactory<AuditSampleFactory> */
    use HasFactory;

    protected $fillable = [
        'screening_id',
        'correct',
        'staff_id',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'correct' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Screening, $this>
     */
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function isPending(): bool
    {
        return $this->correct === null;
    }
}
