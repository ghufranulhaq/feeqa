<?php

namespace App\Actions\Staff;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Actions\Businesses\RequestReviewVerification;
use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Moderation\ModerationVerb;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\GuidelineVersion;
use App\Models\Review;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-12, FR-006-13: the five FR-006-12 verbs that act on a single
 * review today. `Review::status` only has three values (published, held,
 * rejected) — `remove` and `mark_not_genuine` both land on `rejected`,
 * the same status `ScreenReviewSubmission`'s own auto-reject uses, since
 * both remove the review from every publicly-visible/scored surface; the
 * two verbs stay distinct because `mark_not_genuine` requires its own
 * reason code and always re-triggers `RecalculateBusinessScore`
 * (FR-006-17), while `remove` may carry any reason code and only
 * recalculates when the review had been published.
 */
class ModerateReview
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, Review $review, ModerationVerb $verb, ReasonCode $reasonCode, ?string $redactedSpan = null): Review
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can moderate a review.');
        }

        // Edge cases table: a moderator conflicted on this review's
        // Business is blocked from acting on it.
        if ($review->business->hasConflictWithStaff($staff)) {
            throw new AuthorizationException('You have a declared conflict of interest with this business.');
        }

        $wasPublished = $review->status === ReviewStatus::Published;

        match ($verb) {
            ModerationVerb::Publish => $this->publish($review),
            ModerationVerb::Remove => $review->update(['status' => ReviewStatus::Rejected]),
            ModerationVerb::Redact => $this->redact($review, $redactedSpan),
            ModerationVerb::MarkNotGenuine => $this->markNotGenuine($review, $reasonCode),
            ModerationVerb::RequestVerification => (new RequestReviewVerification)->handle($review->business, $staff, $review, requestedByStaff: true),
        };

        ComplianceLogEntry::record(
            staff: $staff,
            action: "review_{$verb->value}",
            reasonCode: $reasonCode->value,
            target: $review,
        );

        if (in_array($verb, [ModerationVerb::Remove, ModerationVerb::MarkNotGenuine], true) && $wasPublished) {
            app(RecalculateBusinessScore::class)->handle($review->business);
        }

        // FR-006-13: "request verification" already sends its own
        // notification (`BusinessRequestedVerificationNotification`) from
        // inside `RequestReviewVerification` — a second statement of
        // reasons here would be a duplicate, not a distinct decision.
        if ($verb !== ModerationVerb::RequestVerification) {
            $review->reviewer->notify(new StatementOfReasonsNotification(
                whatWasAffected: 'your review of '.$review->business->name,
                reasonCode: $reasonCode,
                guidelineVersion: $this->currentReviewerGuidelineVersion(),
                automated: false,
            ));
        }

        return $review->fresh();
    }

    private function publish(Review $review): void
    {
        if ($review->status === ReviewStatus::Published) {
            throw ValidationException::withMessages(['review' => 'This review is already published.']);
        }

        $review->update([
            'status' => ReviewStatus::Published,
            'published_at' => $review->published_at ?? now(),
        ]);
    }

    /**
     * @throws ValidationException
     */
    private function redact(Review $review, ?string $redactedSpan): void
    {
        if ($redactedSpan === null || $redactedSpan === '' || ! str_contains($review->text, $redactedSpan)) {
            throw ValidationException::withMessages(['redacted_span' => 'The text to redact must exist in the review.']);
        }

        $review->update(['text' => str_replace($redactedSpan, '[removed]', $review->text)]);
    }

    /**
     * @throws ValidationException
     */
    private function markNotGenuine(Review $review, ReasonCode $reasonCode): void
    {
        if ($reasonCode !== ReasonCode::NotGenuine) {
            throw ValidationException::withMessages(['reason_code' => 'Marking a review not genuine requires the not_genuine reason code.']);
        }

        $review->update(['status' => ReviewStatus::Rejected]);
    }

    private function currentReviewerGuidelineVersion(): ?int
    {
        return GuidelineVersion::query()
            ->where('audience', GuidelineAudience::Reviewer)
            ->where('is_current', true)
            ->value('version');
    }
}
