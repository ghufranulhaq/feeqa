<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\IndustryReadinessChecklist;
use App\Models\Business;
use App\Models\Category;

/**
 * FR-002-31: the readiness checklist an industry must show before it can
 * launch. Blocking items are checked first; the warning-only items are
 * always returned (a healthy industry still has to be reviewed and
 * acknowledged, not just an unhealthy one).
 */
class EvaluateIndustryReadiness
{
    /**
     * FR-002-31's "not enough businesses for a reliable benchmark" line
     * (015 FR-015-07 isn't built yet — a placeholder threshold).
     */
    private const MIN_BUSINESSES_FOR_BENCHMARK = 3;

    public function handle(Category $industry): IndustryReadinessChecklist
    {
        return new IndustryReadinessChecklist(
            blockingIssues: $this->blockingIssues($industry),
            warnings: $this->warnings($industry),
        );
    }

    /**
     * @return list<string>
     */
    private function blockingIssues(Category $industry): array
    {
        $issues = [];

        foreach (config('platform.locales.supported') as $locale) {
            if (trim((string) ($industry->name[$locale] ?? '')) === '') {
                $issues[] = "Missing a localised name for \"{$locale}\".";
            }
        }

        if (trim((string) $industry->icon) === '') {
            $issues[] = 'Missing an icon.';
        }

        if (! $industry->children()->exists()) {
            $issues[] = 'Needs at least one sub-category.';
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function warnings(Category $industry): array
    {
        $questionSet = $industry->currentQuestionSet();
        $businessCount = Business::where('primary_category_id', $industry->id)->count();

        return [
            $questionSet === null
                ? 'Question set: none — reviewers will get the generic form.'
                : "Question set: version {$questionSet->version}, {$questionSet->questions()->count()} question(s).",
            'Topic list: not available yet (spec 011).',
            'Default invitation delay: not configured yet (spec 005).',
            "Businesses listed: {$businessCount}.",
            $businessCount >= self::MIN_BUSINESSES_FOR_BENCHMARK
                ? 'Industry benchmark: possible (spec 015).'
                : 'Industry benchmark: not enough businesses yet (spec 015).',
        ];
    }
}
