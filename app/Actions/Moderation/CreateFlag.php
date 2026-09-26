<?php

namespace App\Actions\Moderation;

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Models\Business;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-07 through FR-006-10: any visitor (a guest must give an email),
 * a signed-in reviewer, or a business (`$actingBusiness`, checked for
 * `BusinessPermission::FlagReviews`) may flag a piece of content. Only
 * `Review` is wired to `not_genuine` re-screening today — the same
 * "content types that exist" honest placeholder every other 006 action
 * documents, since replies/case messages/media items (007/010/012) don't
 * exist yet.
 */
class CreateFlag
{
    /**
     * @var list<ReasonCode> reasons that can trigger an automatic blur (FR-006-08)
     */
    private const BLUR_ELIGIBLE_REASONS = [ReasonCode::HarmfulIllegal, ReasonCode::PersonalInfo];

    /**
     * @param  list<array{path: string, size: int}>  $evidence
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        Model $flaggable,
        ?ReasonCode $reasonCode,
        ?User $reporter = null,
        ?string $reporterEmail = null,
        ?string $details = null,
        array $evidence = [],
        ?Business $actingBusiness = null,
    ): Flag {
        if ($reasonCode === null) {
            throw ValidationException::withMessages(['reason_code' => 'A reason code is required.']);
        }

        if ($actingBusiness !== null) {
            if ($reporter === null || ! $actingBusiness->userCan($reporter, BusinessPermission::FlagReviews)) {
                throw new AuthorizationException('You cannot flag content on behalf of this business.');
            }

            // FR-006-14 step 4: a business under a flagging restriction
            // can't file new flags until it's lifted.
            if ($actingBusiness->hasFeatureRestricted(RestrictableFeature::Flagging)) {
                throw new AuthorizationException('Flagging is currently restricted for this business.');
            }
        } elseif ($reporter === null && trim((string) $reporterEmail) === '') {
            // FR-006-07: "non-signed-in reporters must give an email."
            throw ValidationException::withMessages(['reporter_email' => 'An email address is required to flag without an account.']);
        }

        if ($details !== null && mb_strlen($details) > $this->maxDetailsLength()) {
            throw ValidationException::withMessages(['details' => 'Details must be 1,000 characters or fewer.']);
        }

        if (count($evidence) > $this->maxEvidenceFiles()) {
            throw ValidationException::withMessages(['evidence' => 'No more than 5 evidence files are allowed.']);
        }

        foreach ($evidence as $file) {
            if (($file['size'] ?? 0) > $this->maxEvidenceFileBytes()) {
                throw ValidationException::withMessages(['evidence' => 'Each evidence file must be 10 MB or smaller.']);
            }
        }

        $existing = $this->existingFlagBy($flaggable, $reporter, $actingBusiness, $reporterEmail);
        if ($existing !== null) {
            // Edge case table: "the same user flags the same item twice" —
            // a no-op, returning the flag already on file.
            return $existing;
        }

        if ($actingBusiness !== null) {
            $this->enforceBusinessFlagLimits($actingBusiness);
        } elseif ($reporter !== null) {
            $this->flagIfMassFlagging($reporter);
        }

        $status = $actingBusiness !== null
            ? FlagStatus::Open // FR-006-09: "a business flag must never hide a review by itself."
            : $this->initialStatus($reasonCode, $flaggable, $reporter);

        $flag = Flag::create([
            'flaggable_type' => $flaggable->getMorphClass(),
            'flaggable_id' => $flaggable->getKey(),
            'reporter_id' => $reporter?->id,
            'reporter_email' => $reporter === null ? $reporterEmail : null,
            'reason_code' => $reasonCode,
            'details' => $details,
            'evidence_paths' => array_values(array_column($evidence, 'path')) ?: null,
            'status' => $status,
            'is_business_flag' => $actingBusiness !== null,
            'business_id' => $actingBusiness?->id,
            'sla_due_at' => now()->add($this->slaFor($reasonCode)),
        ]);

        if ($reasonCode === ReasonCode::NotGenuine && $flaggable instanceof Review) {
            $this->rerunScreening($flaggable);
        }

        return $flag;
    }

    private function existingFlagBy(Model $flaggable, ?User $reporter, ?Business $actingBusiness, ?string $reporterEmail): ?Flag
    {
        $query = Flag::query()
            ->where('flaggable_type', $flaggable->getMorphClass())
            ->where('flaggable_id', $flaggable->getKey());

        if ($actingBusiness !== null) {
            return $query->where('business_id', $actingBusiness->id)->first();
        }

        if ($reporter !== null) {
            return $query->where('reporter_id', $reporter->id)->first();
        }

        return $query->whereNull('reporter_id')->where('reporter_email', $reporterEmail)->first();
    }

    /**
     * FR-006-08: blur when the reporter is trusted, or once at least
     * `blur_min_distinct_reporters` distinct reporters (this one included)
     * have flagged the same item for a harmful/personal-info reason.
     */
    private function initialStatus(ReasonCode $reasonCode, Model $flaggable, ?User $reporter): FlagStatus
    {
        if (! in_array($reasonCode, self::BLUR_ELIGIBLE_REASONS, true)) {
            return FlagStatus::Open;
        }

        if ($reporter !== null && ! $reporter->hasUpheldFlagAgainstThem()) {
            return FlagStatus::Blurred;
        }

        $distinctReporters = $this->distinctReporterCount($flaggable);

        if ($distinctReporters + 1 >= $this->blurMinDistinctReporters()) {
            return FlagStatus::Blurred;
        }

        return FlagStatus::Open;
    }

    private function distinctReporterCount(Model $flaggable): int
    {
        return Flag::query()
            ->where('flaggable_type', $flaggable->getMorphClass())
            ->where('flaggable_id', $flaggable->getKey())
            ->whereIn('reason_code', self::BLUR_ELIGIBLE_REASONS)
            ->get(['reporter_id', 'reporter_email'])
            ->map(fn (Flag $flag) => $flag->reporter_id !== null ? 'u:'.$flag->reporter_id : 'e:'.$flag->reporter_email)
            ->unique()
            ->count();
    }

    /**
     * FR-006-08: harmful/personal-info flags carry a 24h SLA regardless of
     * whether this particular one triggered a blur; everything else is 7
     * days.
     */
    private function slaFor(ReasonCode $reasonCode): \DateInterval
    {
        return in_array($reasonCode, self::BLUR_ELIGIBLE_REASONS, true)
            ? new \DateInterval("PT{$this->harmfulSlaHours()}H")
            : new \DateInterval("P{$this->defaultSlaDays()}D");
    }

    /**
     * FR-006-09: re-run T2's screening engine against the flagged review's
     * own stored text — advisory only, feeding the moderation queue (T5);
     * the review itself stays visible (scenario 3) until staff act.
     */
    private function rerunScreening(Review $review): void
    {
        $outcome = (new ScreenReviewSubmission)->handle($review->reviewer, $review->business, $review->title, $review->text);

        (new ScreenReviewSubmission)->record($review, $outcome);
    }

    /**
     * Edge case table: "mass flagging by one account (> 20 flags/hour)."
     * A documented judgment call (same shape as 004's and 005's own
     * honest-signal-now placeholders): logged for staff, not hard-rejected
     * — wrongly refusing a batch of genuine harmful-content reports would
     * fight FR-006-08's own SLA.
     */
    private function flagIfMassFlagging(User $reporter): void
    {
        $countLastHour = Flag::query()
            ->where('reporter_id', $reporter->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($countLastHour >= $this->massFlaggingHourlyThreshold()) {
            Log::warning('Mass flagging by one account exceeds 20/hour (FR-006-10 edge case)', [
                'reporter_id' => $reporter->id,
                'flags_in_last_hour' => $countLastHour + 1,
            ]);
        }
    }

    /**
     * FR-006-10: the 50-open-flags cap is a hard stop; the reject-rate
     * check is a logged educational-notice signal (feeding T6's ladder
     * step 1), same alert shape as 005 T10.
     *
     * @throws ValidationException
     */
    private function enforceBusinessFlagLimits(Business $business): void
    {
        $openCount = Flag::query()
            ->where('business_id', $business->id)
            ->whereIn('status', [FlagStatus::Open, FlagStatus::Blurred])
            ->count();

        if ($openCount >= $this->businessOpenFlagCap()) {
            throw ValidationException::withMessages([
                'business' => 'This business already has the maximum number of open flags.',
            ]);
        }

        $windowDays = $this->businessRejectRateWindowDays();
        $recent = Flag::query()
            ->where('business_id', $business->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->get('status');

        if ($recent->count() < $this->businessRejectRateMinFlags()) {
            return;
        }

        $rejectedShare = $recent->filter(fn (Flag $flag) => $flag->status === FlagStatus::Rejected)->count() / $recent->count();

        if ($rejectedShare > $this->businessRejectRateThreshold()) {
            Log::warning('Business flags rejected more than 80% of the time over 90 days (FR-006-10)', [
                'business_id' => $business->id,
                'flags_in_window' => $recent->count(),
                'rejected_share' => $rejectedShare,
            ]);
        }
    }

    private function maxDetailsLength(): int
    {
        return (int) config('platform.moderation.flags.max_details_length', 1000);
    }

    private function maxEvidenceFiles(): int
    {
        return (int) config('platform.moderation.flags.max_evidence_files', 5);
    }

    private function maxEvidenceFileBytes(): int
    {
        return (int) config('platform.moderation.flags.max_evidence_file_bytes', 10 * 1024 * 1024);
    }

    private function blurMinDistinctReporters(): int
    {
        return (int) config('platform.moderation.flags.blur_min_distinct_reporters', 3);
    }

    private function harmfulSlaHours(): int
    {
        return (int) config('platform.moderation.flags.harmful_sla_hours', 24);
    }

    private function defaultSlaDays(): int
    {
        return (int) config('platform.moderation.flags.default_sla_days', 7);
    }

    private function massFlaggingHourlyThreshold(): int
    {
        return (int) config('platform.moderation.flags.mass_flagging_hourly_threshold', 20);
    }

    private function businessOpenFlagCap(): int
    {
        return (int) config('platform.moderation.flags.business_open_flag_cap', 50);
    }

    private function businessRejectRateWindowDays(): int
    {
        return (int) config('platform.moderation.flags.business_reject_rate_window_days', 90);
    }

    private function businessRejectRateMinFlags(): int
    {
        return (int) config('platform.moderation.flags.business_reject_rate_min_flags', 20);
    }

    private function businessRejectRateThreshold(): float
    {
        return (float) config('platform.moderation.flags.business_reject_rate_threshold', 0.8);
    }
}
