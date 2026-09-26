<?php

namespace Database\Seeders\Base;

use App\Domain\Moderation\GuidelineAudience;
use App\Models\GuidelineVersion;
use Illuminate\Database\Seeder;

/**
 * FR-006-01: v1 of both guideline audiences, needed in every environment
 * (a Business or Reviewer must always be able to read the rules they
 * agreed to). Safe to run every deploy: updateOrCreate by
 * [audience, version], and it never touches a version once published.
 */
class GuidelineVersionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsert(GuidelineAudience::Reviewer, 1, <<<'MD'
            # Reviewer Guidelines (v1)

            A review must come from a real person describing a real experience.

            - **Genuine experience.** Only write about a purchase, booking, or
              service interaction that actually happened to you.
            - **12-month window.** The experience must be within the last 12
              months, except for a dated lifecycle update to a review you've
              already published.
            - **One review per experience.** Don't post more than one review
              for the same experience.
            - **18 or older.** You must be at least 18 to write a review.
            - **No incentives.** Never accept payment, a discount, or any other
              reward for writing, editing, or removing a review.
            - **No conflicts of interest.** Don't review a business you own,
              work for, or compete with, without disclosing it as an insider
              review.

            ## Content that isn't allowed

            - `harmful_illegal` — hate speech, threats, violence, obscenity,
              defamation, or terrorism.
            - `personal_info` — phone numbers, addresses, or other identifying
              details about a named individual.
            - `advertising_spam` — promotional content unrelated to your
              experience.
            - `not_genuine` — a review that isn't based on a real experience.
            - `incentivised` — a review written in exchange for a reward.
            - `conflict_of_interest` — an undisclosed insider review.
            - `wrong_business` — a review posted about the wrong business.
            - `off_topic` — political or religious advocacy unrelated to the
              experience.
            - `ai_generated_deceptive` — AI-generated text presented as a
              genuine personal account.
            - `ip_infringement` — content that infringes someone else's
              copyright or trademark.
            - `other_illegal` — anything else that breaks the law where you or
              the business are based.

            Breaking these rules can lead to your review being removed and,
            for repeated or severe breaches, your account being blocked. Every
            decision comes with a statement of reasons and a right to appeal.
            MD);

        $this->upsert(GuidelineAudience::Business, 1, <<<'MD'
            # Business Guidelines (v1)

            Businesses can reply to reviews, request verification, and flag
            content — but reviewers own their voice.

            - You **cannot** edit, delete, hide, or delay the publication of a
              review. You can only reply, flag it with a reason, request
              verification, or open a case.
            - You must never offer money, discounts, or any other incentive
              for a review, and never selectively invite only customers you
              expect to be happy ("review gating").
            - Flagging a review as `not_genuine` re-runs our automated checks,
              but never hides the review by itself — only a moderator can do
              that.
            - Buying reviews, posting reviews about your own business, or
              coordinating fake reviews leads to the enforcement ladder,
              including a public Consumer Warning, hidden scores, and
              suspended paid features.

            The same ten prohibited-content categories in the Reviewer
            Guidelines apply to anything a business submits: replies, profile
            content, and evidence attached to a flag.

            Every enforcement action against your account comes with a
            statement of reasons and a right to appeal.
            MD);
    }

    private function upsert(GuidelineAudience $audience, int $version, string $body): void
    {
        GuidelineVersion::query()->updateOrCreate(
            ['audience' => $audience, 'version' => $version],
            ['body' => $body, 'published_at' => now(), 'is_current' => true],
        );
    }
}
