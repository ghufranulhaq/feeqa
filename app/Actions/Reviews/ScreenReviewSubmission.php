<?php

namespace App\Actions\Reviews;

use App\Domain\Invitations\GuardNeutralTemplate;
use App\Models\Business;
use App\Models\Review;
use App\Models\Screening;
use App\Models\ScreeningRuleState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * FR-006-03 through FR-006-05: rules-only automated screening (still not
 * a driver — see the config's own comment on `reviews.screening` for why
 * constitution §5.1's provider pattern doesn't apply here). Runs on every
 * review submission (003 T3), edit (003 T10), and lifecycle update (003
 * T17) — the three call sites each persist their own `Screening` record
 * (006 T2) right after saving the content this outcome judged.
 *
 * Two rules are registered as auto-reject-eligible (FR-006-05's own
 * examples): an exact-duplicate text cluster and the blocklist-word
 * match. `ScreeningRuleState` can disable either one (006 T8's weekly
 * audit); a disabled rule still fires, but only ever recommends `hold`.
 * Every other signal only ever pushes the recommendation to `hold` via
 * the weighted risk score, never to `reject`.
 */
class ScreenReviewSubmission
{
    public const RULE_BLOCKLIST_WORD = 'blocklist_word';

    public const RULE_EXACT_DUPLICATE_CLUSTER = 'exact_duplicate_cluster';

    public const RULE_NEAR_IDENTICAL_CROSS_BUSINESS = 'near_identical_cross_business';

    public const RULE_COORDINATED_DUPLICATE_CLUSTER = 'coordinated_duplicate_cluster';

    public const RULE_PERSONAL_INFO = 'personal_info_detected';

    /**
     * @var list<string> rules allowed to recommend `reject` while enabled (FR-006-05)
     */
    private const AUTO_REJECT_RULES = [
        self::RULE_BLOCKLIST_WORD,
        self::RULE_EXACT_DUPLICATE_CLUSTER,
    ];

    /**
     * FR-006-20: `ComputeRulePrecision` (T8) only ever measures and
     * disables this registry's own rules — every other signal already
     * never recommends `reject`, so it has no precision bar to fail.
     *
     * @return list<string>
     */
    public static function autoRejectRuleIds(): array
    {
        return self::AUTO_REJECT_RULES;
    }

    public function handle(User $reviewer, Business $business, string $title, string $text, ?string $ipAddress = null): ScreeningOutcome
    {
        // FR-006-06: a staff-ordered freeze holds everything on this
        // Business, full stop — no other signal is even computed.
        if ($business->hasActiveModerationFreeze()) {
            return ScreeningOutcome::held("We're checking unusual activity on this profile.", 0.0, ['business_frozen']);
        }

        $signals = $this->computeSignals($reviewer, $business, $title, $text, $ipAddress);
        $exportSignals = collect($signals)->except('triggered_rules')->all();
        $riskScore = $this->riskScore($signals);

        if ($signals['blocklist_hit']) {
            return $this->outcomeFor(
                self::RULE_BLOCKLIST_WORD,
                "Your review contains language that isn't allowed.",
                $signals,
                $exportSignals,
            );
        }

        if ($signals['exact_duplicate_cluster_size'] >= $this->exactDuplicateMinAccounts() - 1) {
            return $this->outcomeFor(
                self::RULE_EXACT_DUPLICATE_CLUSTER,
                'Held for review: this text matches several other accounts\' reviews.',
                $signals,
                $exportSignals,
            );
        }

        if ($signals['personal_info_detected']) {
            return ScreeningOutcome::held(
                'Held for review: this may contain personal information.',
                $riskScore,
                $signals['triggered_rules'],
                $exportSignals,
            );
        }

        if ($signals['near_identical_cross_business']) {
            return ScreeningOutcome::held(
                'Held for review: this text closely matches a review you posted about a different business.',
                $riskScore,
                $signals['triggered_rules'],
                $exportSignals,
            );
        }

        if ($signals['coordinated_cluster_size'] >= $this->coordinatedClusterMinAccounts() - 1) {
            return ScreeningOutcome::held(
                'Held for review: this text closely matches other reviews posted about this business recently.',
                $riskScore,
                $signals['triggered_rules'],
                $exportSignals,
            );
        }

        if ($riskScore >= (float) config('platform.reviews.screening.hold_risk_score_threshold', 0.5)) {
            return ScreeningOutcome::held(
                'Held for review: unusual signals were detected on this submission.',
                $riskScore,
                $signals['triggered_rules'],
                $exportSignals,
            );
        }

        return ScreeningOutcome::published($riskScore, $signals['triggered_rules'], $exportSignals);
    }

    /**
     * FR-006-03: writes the immutable audit record for content that has
     * just been created or updated, using the outcome `handle()` already
     * computed for it. Called by the three sites above right after they
     * persist their own content.
     */
    public function record(Model $content, ScreeningOutcome $outcome): Screening
    {
        return Screening::create([
            'screenable_type' => $content->getMorphClass(),
            'screenable_id' => $content->getKey(),
            'recommendation' => $outcome->status,
            'risk_score' => $outcome->riskScore,
            'triggered_rules' => $outcome->triggeredRules,
            'signals' => $outcome->signals,
            'reason' => $outcome->reason,
        ]);
    }

    /**
     * @return array{
     *     account_age_days: int, reviewer_review_count: int,
     *     reviewer_velocity_24h: int, business_velocity_1h: int,
     *     blocklist_hit: bool, exact_duplicate_cluster_size: int,
     *     near_identical_cross_business: bool, coordinated_cluster_size: int,
     *     incentive_language: bool, personal_info_detected: bool,
     *     contains_link: bool, network_flagged: bool,
     *     triggered_rules: list<string>,
     * }
     */
    private function computeSignals(User $reviewer, Business $business, string $title, string $text, ?string $ipAddress): array
    {
        $normalized = $this->normalize($text);
        $triggeredRules = [];

        $blocklistHit = $this->matchesBlocklist($title) || $this->matchesBlocklist($text);
        if ($blocklistHit) {
            $triggeredRules[] = self::RULE_BLOCKLIST_WORD;
        }

        $exactDuplicateClusterSize = $normalized === '' ? 0 : $this->exactDuplicateClusterSize($reviewer, $normalized);
        if ($exactDuplicateClusterSize >= $this->exactDuplicateMinAccounts() - 1) {
            $triggeredRules[] = self::RULE_EXACT_DUPLICATE_CLUSTER;
        }

        $nearIdenticalCrossBusiness = $normalized !== '' && $this->isNearIdenticalToAnotherBusiness($reviewer, $business, $normalized);
        if ($nearIdenticalCrossBusiness) {
            $triggeredRules[] = self::RULE_NEAR_IDENTICAL_CROSS_BUSINESS;
        }

        $coordinatedClusterSize = $normalized === '' ? 0 : $this->coordinatedClusterSize($reviewer, $business, $normalized);
        if ($coordinatedClusterSize >= $this->coordinatedClusterMinAccounts() - 1) {
            $triggeredRules[] = self::RULE_COORDINATED_DUPLICATE_CLUSTER;
        }

        $incentiveLanguage = $this->mentionsIncentiveLanguage($title.' '.$text);
        if ($incentiveLanguage) {
            $triggeredRules[] = 'incentive_language';
        }

        $personalInfoDetected = $this->containsPersonalInfo($text);
        if ($personalInfoDetected) {
            $triggeredRules[] = self::RULE_PERSONAL_INFO;
        }

        $containsLink = $this->containsLink($text);
        if ($containsLink) {
            $triggeredRules[] = 'contains_link';
        }

        $networkFlagged = $ipAddress !== null && $this->isFlaggedNetwork($ipAddress);
        if ($networkFlagged) {
            $triggeredRules[] = 'network_reputation';
        }

        $accountAgeDays = (int) $reviewer->created_at?->diffInDays(now());
        $reviewerReviewCount = Review::query()->where('reviewer_id', $reviewer->id)->count();

        $accountNew = $accountAgeDays < (int) config('platform.reviews.screening.new_account_days', 2);
        if ($accountNew) {
            $triggeredRules[] = 'new_account';
        }

        $reviewerVelocity24h = Review::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($reviewerVelocity24h >= (int) config('platform.reviews.screening.reviewer_velocity_24h_threshold', 5)) {
            $triggeredRules[] = 'reviewer_high_velocity';
        }

        $businessVelocity1h = Review::query()
            ->where('business_id', $business->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($businessVelocity1h >= (int) config('platform.reviews.screening.business_velocity_1h_threshold', 10)) {
            $triggeredRules[] = 'business_high_velocity';
        }

        return [
            'account_age_days' => $accountAgeDays,
            'reviewer_review_count' => $reviewerReviewCount,
            'reviewer_velocity_24h' => $reviewerVelocity24h,
            'business_velocity_1h' => $businessVelocity1h,
            'blocklist_hit' => $blocklistHit,
            'exact_duplicate_cluster_size' => $exactDuplicateClusterSize,
            'near_identical_cross_business' => $nearIdenticalCrossBusiness,
            'coordinated_cluster_size' => $coordinatedClusterSize,
            'incentive_language' => $incentiveLanguage,
            'personal_info_detected' => $personalInfoDetected,
            'contains_link' => $containsLink,
            'network_flagged' => $networkFlagged,
            'triggered_rules' => $triggeredRules,
        ];
    }

    /**
     * FR-006-05: a documented, narrow, precision-audited (T8) rule —
     * downgraded to `hold` the moment `ScreeningRuleState` disables it.
     *
     * @param  array<string, mixed>  $signals
     * @param  array<string, mixed>  $exportSignals
     */
    private function outcomeFor(string $ruleId, string $reason, array $signals, array $exportSignals): ScreeningOutcome
    {
        $riskScore = $this->riskScore($signals);
        $triggeredRules = $signals['triggered_rules'];

        if (in_array($ruleId, self::AUTO_REJECT_RULES, true) && ScreeningRuleState::isEnabled($ruleId)) {
            return ScreeningOutcome::rejected($reason, max($riskScore, 0.9), $triggeredRules, $exportSignals);
        }

        return ScreeningOutcome::held($reason, $riskScore, $triggeredRules, $exportSignals);
    }

    /**
     * FR-006-04: a simple, deterministic weighted sum — the auto-reject
     * and standalone-hold signals above already short-circuit before this
     * is used to decide anything; here it only ever pushes the
     * recommendation to `hold` past the configured threshold.
     *
     * @param  array<string, mixed>  $signals
     */
    private function riskScore(array $signals): float
    {
        $score = 0.0;
        $score += $signals['near_identical_cross_business'] ? 0.3 : 0.0;
        $score += $signals['coordinated_cluster_size'] > 0 ? 0.3 : 0.0;
        $score += $signals['incentive_language'] ? 0.2 : 0.0;
        $score += $signals['personal_info_detected'] ? 0.3 : 0.0;
        $score += $signals['contains_link'] ? 0.05 : 0.0;
        $score += $signals['network_flagged'] ? 0.2 : 0.0;
        $score += $signals['account_age_days'] < (int) config('platform.reviews.screening.new_account_days', 2) ? 0.1 : 0.0;
        $score += $signals['reviewer_velocity_24h'] >= (int) config('platform.reviews.screening.reviewer_velocity_24h_threshold', 5) ? 0.15 : 0.0;
        $score += $signals['business_velocity_1h'] >= (int) config('platform.reviews.screening.business_velocity_1h_threshold', 10) ? 0.1 : 0.0;

        return round(min(1.0, $score), 2);
    }

    private function matchesBlocklist(string $value): bool
    {
        $haystack = mb_strtolower($value);

        /** @var list<string> $blocklist */
        $blocklist = config('platform.reviews.screening.blocklist_words', []);

        foreach ($blocklist as $word) {
            $word = mb_strtolower(trim($word));

            if ($word !== '' && str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * FR-006-05: exact text (after normalization) shared by other
     * accounts, any Business, within a wider window — the narrow,
     * high-precision rule allowed to auto-reject.
     */
    private function exactDuplicateClusterSize(User $reviewer, string $normalized): int
    {
        $windowHours = (int) config('platform.reviews.screening.exact_duplicate_window_hours', 24);

        return Review::query()
            ->where('reviewer_id', '!=', $reviewer->id)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->select('reviewer_id', 'text')
            ->get()
            ->filter(fn (Review $candidate) => $this->normalize($candidate->text) === $normalized)
            ->pluck('reviewer_id')
            ->unique()
            ->count();
    }

    private function isNearIdenticalToAnotherBusiness(User $reviewer, Business $business, string $normalized): bool
    {
        $windowDays = (int) config('platform.reviews.screening.near_identical_window_days', 30);
        $threshold = (int) config('platform.reviews.screening.near_identical_similarity_threshold', 90);

        $candidates = Review::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('business_id', '!=', $business->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->pluck('text');

        foreach ($candidates as $candidate) {
            similar_text($normalized, $this->normalize($candidate), $percent);

            if ($percent >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * User Scenario 1: near-identical (not necessarily exact) text posted
     * about the *same* Business by several *distinct* reviewers within a
     * short window — a coordinated-fraud shape that only ever holds,
     * never auto-rejects, because it isn't an exact match.
     */
    private function coordinatedClusterSize(User $reviewer, Business $business, string $normalized): int
    {
        $windowHours = (int) config('platform.reviews.screening.coordinated_cluster_window_hours', 1);
        $threshold = (int) config('platform.reviews.screening.near_identical_similarity_threshold', 90);

        $candidates = Review::query()
            ->where('business_id', $business->id)
            ->where('reviewer_id', '!=', $reviewer->id)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->select('reviewer_id', 'text')
            ->get();

        $matchingReviewers = [];

        foreach ($candidates as $candidate) {
            similar_text($normalized, $this->normalize($candidate->text), $percent);

            if ($percent >= $threshold) {
                $matchingReviewers[$candidate->reviewer_id] = true;
            }
        }

        return count($matchingReviewers);
    }

    private function mentionsIncentiveLanguage(string $text): bool
    {
        $lower = mb_strtolower($text);

        foreach (GuardNeutralTemplate::incentiveTerms('en-GB') as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * FR-006-02 `personal_info`: a phone number or email address in free
     * text. Deliberately narrow (matches the edge case table: a first
     * name alone is allowed) — this is a submission-time signal, not the
     * final word; a flag (T4) handles anything this misses.
     */
    private function containsPersonalInfo(string $text): bool
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[a-zA-Z]{2,}/', $text) === 1) {
            return true;
        }

        return preg_match('/(?:\+\d{1,3}[\s.-]?)?(?:\(?\d{2,5}\)?[\s.-]?){2,5}\d{3,4}/', $text) === 1
            && preg_match('/\d{7,}/', preg_replace('/[\s().-]/', '', $text) ?? '') === 1;
    }

    private function containsLink(string $text): bool
    {
        return preg_match('#https?://|www\.#i', $text) === 1;
    }

    private function isFlaggedNetwork(string $ipAddress): bool
    {
        /** @var list<string> $ranges */
        $ranges = config('platform.moderation.network.known_bad_ranges', []);

        return $ranges !== [] && IpUtils::checkIp($ipAddress, $ranges);
    }

    private function exactDuplicateMinAccounts(): int
    {
        return (int) config('platform.reviews.screening.exact_duplicate_min_accounts', 3);
    }

    private function coordinatedClusterMinAccounts(): int
    {
        return (int) config('platform.reviews.screening.coordinated_cluster_min_accounts', 3);
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($value)) ?? '');
    }
}
