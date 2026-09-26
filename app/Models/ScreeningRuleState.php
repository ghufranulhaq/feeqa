<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FR-006-05, FR-006-20: an opt-out toggle for the auto-reject rules
 * registered in `App\Actions\Reviews\ScreenReviewSubmission`. A rule with
 * no row is enabled; only the weekly audit (T8) ever disables one, when
 * its measured precision falls under 99%.
 */
class ScreeningRuleState extends Model
{
    protected $primaryKey = 'rule_id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'rule_id',
        'enabled',
        'disabled_at',
        'disabled_reason',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public static function isEnabled(string $ruleId): bool
    {
        return static::query()->whereKey($ruleId)->value('enabled') ?? true;
    }

    public static function disable(string $ruleId, string $reason): void
    {
        static::query()->updateOrCreate(
            ['rule_id' => $ruleId],
            ['enabled' => false, 'disabled_at' => now(), 'disabled_reason' => $reason],
        );
    }
}
