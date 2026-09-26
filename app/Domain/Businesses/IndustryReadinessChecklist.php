<?php

namespace App\Domain\Businesses;

/**
 * FR-002-31: the blocking items reject a launch outright; the warnings
 * are shown for staff to review, and launching requires acknowledging
 * them (App\Actions\Staff\LaunchIndustry's $acknowledgeWarnings), whatever
 * they say.
 */
final class IndustryReadinessChecklist
{
    /**
     * @param  list<string>  $blockingIssues
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly array $blockingIssues,
        public readonly array $warnings,
    ) {}

    public function isBlocked(): bool
    {
        return $this->blockingIssues !== [];
    }
}
