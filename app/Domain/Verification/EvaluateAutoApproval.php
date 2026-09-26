<?php

namespace App\Domain\Verification;

use Illuminate\Support\Carbon;

/**
 * FR-004-06: a proof auto-approves only if the merchant matches, the
 * transaction date falls in the allowed window, the reference hasn't been
 * used before (FR-004-10), and tamper checks pass (FR-004-07). Any single
 * failure holds it for the T5 staff queue with the specific reason
 * recorded — never a silent reject.
 */
final class EvaluateAutoApproval
{
    public function handle(
        bool $merchantMatches,
        ?Carbon $transactionDate,
        Carbon $dateOfExperience,
        bool $referenceAlreadyUsed,
        TamperSignalResult $tamperSignals,
    ): AutoApprovalOutcome {
        if (! $merchantMatches) {
            return AutoApprovalOutcome::hold('merchant_mismatch');
        }

        if ($transactionDate === null || ! $this->dateWithinWindow($transactionDate, $dateOfExperience)) {
            return AutoApprovalOutcome::hold('date_out_of_window');
        }

        if ($referenceAlreadyUsed) {
            return AutoApprovalOutcome::hold('reference_reused');
        }

        if (! $tamperSignals->passed) {
            return AutoApprovalOutcome::hold($tamperSignals->reasonCode ?? 'tamper_check_failed');
        }

        return AutoApprovalOutcome::approved();
    }

    /**
     * FR-004-06: "within 12 months and not after the date of experience +
     * 30 days." The 12-month age is measured from now (decision time), the
     * same reference point as L6's "experience within 12 months of the
     * review" — a proof for a stale claim doesn't get fresher by waiting.
     */
    private function dateWithinWindow(Carbon $transactionDate, Carbon $dateOfExperience): bool
    {
        $maxAgeMonths = (int) config('platform.verification.auto_approval.max_age_months', 12);
        $graceDays = (int) config('platform.verification.auto_approval.grace_days_after_experience', 30);

        if ($transactionDate->lt(Carbon::now()->subMonths($maxAgeMonths))) {
            return false;
        }

        return $transactionDate->lte($dateOfExperience->copy()->addDays($graceDays));
    }
}
