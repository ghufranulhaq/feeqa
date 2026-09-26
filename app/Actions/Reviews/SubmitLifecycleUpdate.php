<?php

namespace App\Actions\Reviews;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Domain\Reviews\DurabilitySignal;
use App\Domain\Reviews\LifecycleMilestone;
use App\Domain\Reviews\ReviewStatus;
use App\Models\CategoryQuestion;
use App\Models\Review;
use App\Models\ReviewLifecycleUpdate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * FR-003-17 through FR-003-22: author-only, inside the currently open
 * milestone window, re-screened just like the original review (T2).
 */
class SubmitLifecycleUpdate
{
    public function __construct(private readonly ScreenReviewSubmission $screening) {}

    /**
     * @param  array{star_rating: int, text: string, answers?: array<string, mixed>}  $data
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, Review $review, array $data): ReviewLifecycleUpdate
    {
        if ($review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the author can add an update.');
        }

        // Edge case: "Update submitted for a deleted or removed review."
        if ($review->trashed() || $review->status !== ReviewStatus::Published || $review->published_at === null) {
            throw ValidationException::withMessages(['review' => 'This review cannot receive an update.']);
        }

        $milestone = $this->openMilestone($review);

        if ($milestone === null) {
            throw ValidationException::withMessages(['milestone' => $this->windowMessage($review)]);
        }

        $starRating = ReviewFieldGuards::starRating($data['star_rating']);
        $text = ReviewFieldGuards::lifecycleUpdateText($data['text']);
        $answers = $this->guardAnswers($review, $data['answers'] ?? []);

        $outcome = $this->screening->handle($review->reviewer, $review->business, '', $text);

        $update = $review->lifecycleUpdates()->create([
            'milestone' => $milestone,
            'status' => $outcome->status,
            'star_rating' => $starRating,
            'text' => $text,
            'answers' => $answers,
            'published_at' => $outcome->status === ReviewStatus::Published ? now() : null,
        ]);

        if ($outcome->status === ReviewStatus::Published) {
            $this->recalculateDurabilitySignal($review);
        }

        // FR-003-30: the review's own current rating can change here
        // (FR-003-21), which is enough to require a recalculation, held
        // or not — spec 008 decides eligibility once it exists.
        app(RecalculateBusinessScore::class)->handle($review->business);

        return $update;
    }

    /**
     * FR-003-18: the one milestone (if any) whose window is open right
     * now and hasn't already received an update.
     */
    private function openMilestone(Review $review): ?LifecycleMilestone
    {
        $now = now();

        foreach (LifecycleMilestone::cases() as $milestone) {
            if ($review->lifecycleUpdates()->where('milestone', $milestone)->exists()) {
                continue;
            }

            $opensAt = $milestone->windowOpensAt($review->published_at);
            $closesAt = $milestone->windowClosesAt($review->published_at);

            if ($now->greaterThanOrEqualTo($opensAt) && $now->lessThan($closesAt)) {
                return $milestone;
            }
        }

        return null;
    }

    /**
     * Edge case: "Lifecycle update submitted outside a window: Reject:
     * 'Your next update opens on <date>'."
     */
    private function windowMessage(Review $review): string
    {
        $now = now();

        foreach (LifecycleMilestone::cases() as $milestone) {
            if ($review->lifecycleUpdates()->where('milestone', $milestone)->exists()) {
                continue;
            }

            $opensAt = $milestone->windowOpensAt($review->published_at);

            if ($opensAt->greaterThan($now)) {
                return "Your next update opens on {$opensAt->toDateString()}.";
            }
        }

        return 'No more update windows are available for this review.';
    }

    /**
     * FR-003-20: answers are optional here (unlike a fresh review, no
     * question is required), any question not in the effective set is
     * dropped and logged.
     *
     * @param  array<string, mixed>  $rawAnswers
     * @return ?array<string, mixed>
     */
    private function guardAnswers(Review $review, array $rawAnswers): ?array
    {
        if ($rawAnswers === []) {
            return null;
        }

        $category = $review->business->primaryCategory;

        if ($category === null) {
            return null;
        }

        $questions = $category->effectiveQuestions();
        $knownKeys = $questions->pluck('key')->all();
        $answers = [];

        /** @var CategoryQuestion $question */
        foreach ($questions as $question) {
            if (array_key_exists($question->key, $rawAnswers)) {
                $answers[$question->key] = $rawAnswers[$question->key];
            }
        }

        foreach (array_diff(array_keys($rawAnswers), $knownKeys) as $unknownKey) {
            Log::info('Dropped a lifecycle update answer for a question not in the effective set', [
                'review_id' => $review->id,
                'category_id' => $category->id,
                'question_key' => $unknownKey,
            ]);
        }

        return $answers === [] ? null : $answers;
    }

    /**
     * FR-003-22.
     */
    private function recalculateDurabilitySignal(Review $review): void
    {
        $review->update([
            'durability_signal' => DurabilitySignal::fromRatings($review->star_rating, $review->currentRating()),
        ]);
    }
}
