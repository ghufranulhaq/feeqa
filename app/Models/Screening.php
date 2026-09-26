<?php

namespace App\Models;

use App\Domain\Reviews\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FR-006-03, FR-006-04: one immutable screening record per piece of
 * content — never updated after creation, only ever superseded by a new
 * one on a later edit (`UpdateReview`, `SubmitLifecycleUpdate` each write
 * their own).
 *
 * @property ReviewStatus $recommendation
 * @property float $risk_score
 * @property list<string> $triggered_rules
 * @property array<string, mixed> $signals
 */
class Screening extends Model
{
    protected $fillable = [
        'screenable_type',
        'screenable_id',
        'recommendation',
        'risk_score',
        'triggered_rules',
        'signals',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'recommendation' => ReviewStatus::class,
            'risk_score' => 'float',
            'triggered_rules' => 'array',
            'signals' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function screenable(): MorphTo
    {
        return $this->morphTo();
    }
}
