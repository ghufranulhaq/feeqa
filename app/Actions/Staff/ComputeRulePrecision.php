<?php

namespace App\Actions\Staff;

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Models\AuditSample;
use App\Models\ScreeningRuleState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FR-006-05, FR-006-20: "any auto-reject rule that falls below 99%
 * precision is disabled automatically." Only `ScreenReviewSubmission`'s
 * registered auto-reject rules are checked — every other signal already
 * never recommends `reject`, so it has nothing to be measured against.
 * Precision is decided-samples-only (`correct` not null); a rule with no
 * decided samples yet in the window is left alone rather than disabled
 * on zero evidence.
 */
class ComputeRulePrecision
{
    private const PRECISION_THRESHOLD = 0.99;

    private const TRAILING_WINDOW_DAYS = 90;

    /**
     * @return Collection<string, float> precision per rule_id, decided samples only
     */
    public function handle(?Carbon $asOf = null): Collection
    {
        $windowStart = ($asOf ?? now())->copy()->subDays(self::TRAILING_WINDOW_DAYS);
        $precisionByRule = collect();

        foreach (ScreenReviewSubmission::autoRejectRuleIds() as $ruleId) {
            $decided = AuditSample::query()
                ->whereNotNull('correct')
                ->where('decided_at', '>=', $windowStart)
                ->whereHas('screening', fn ($query) => $query->whereJsonContains('triggered_rules', $ruleId))
                ->get();

            if ($decided->isEmpty()) {
                continue;
            }

            $precision = $decided->where('correct', true)->count() / $decided->count();
            $precisionByRule->put($ruleId, $precision);

            if ($precision < self::PRECISION_THRESHOLD) {
                ScreeningRuleState::disable($ruleId, sprintf(
                    'Precision %.1f%% over the trailing %d days (%d decided samples) fell below the 99%% threshold.',
                    $precision * 100,
                    self::TRAILING_WINDOW_DAYS,
                    $decided->count(),
                ));
            }
        }

        return $precisionByRule;
    }
}
