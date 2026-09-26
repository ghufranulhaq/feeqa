<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\ReviewStatus;

/**
 * FR-003-11: the automated screening decision — always exactly one of
 * `published`, `held`, or `rejected`, with a reason recorded whenever the
 * author needs to see one (FR-003-12). FR-006-03, FR-006-04: also carries
 * the risk score, the rule IDs that fired, and the full computed signal
 * set, so the caller can persist a `Screening` record for the audit
 * sample (006 T8) and the transparency report (006 T9).
 */
final readonly class ScreeningOutcome
{
    /**
     * @param  list<string>  $triggeredRules
     * @param  array<string, mixed>  $signals
     */
    public function __construct(
        public ReviewStatus $status,
        public float $riskScore = 0.0,
        public array $triggeredRules = [],
        public array $signals = [],
        public ?string $reason = null,
    ) {}

    /**
     * @param  list<string>  $triggeredRules
     * @param  array<string, mixed>  $signals
     */
    public static function published(float $riskScore = 0.0, array $triggeredRules = [], array $signals = []): self
    {
        return new self(ReviewStatus::Published, $riskScore, $triggeredRules, $signals);
    }

    /**
     * @param  list<string>  $triggeredRules
     * @param  array<string, mixed>  $signals
     */
    public static function held(string $reason, float $riskScore = 0.0, array $triggeredRules = [], array $signals = []): self
    {
        return new self(ReviewStatus::Held, $riskScore, $triggeredRules, $signals, $reason);
    }

    /**
     * @param  list<string>  $triggeredRules
     * @param  array<string, mixed>  $signals
     */
    public static function rejected(string $reason, float $riskScore = 1.0, array $triggeredRules = [], array $signals = []): self
    {
        return new self(ReviewStatus::Rejected, $riskScore, $triggeredRules, $signals, $reason);
    }
}
