<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\CategoryQuestionSet;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-19, FR-002-20, FR-002-21: staff-only. Publishing a new version
 * never touches an earlier one — existing reviews keep citing whichever
 * version they were answered under (spec 003).
 */
class PublishCategoryQuestionSet
{
    /**
     * @param  list<array{key: string, label: array<string, string>, type: string, required?: bool, options?: ?array<string, list<string>>}>  $questions
     *
     * @throws AuthorizationException
     */
    public function handle(Category $category, User $staff, array $questions): CategoryQuestionSet
    {
        if (! in_array($staff->staffRole(), [StaffRole::Admin, StaffRole::SeniorModerator], true)) {
            throw new AuthorizationException('Only an Admin or Senior Moderator can publish a question set.');
        }

        if (count($questions) > CategoryQuestionSet::MAX_QUESTIONS) {
            throw ValidationException::withMessages(['questions' => 'A question set may have at most 8 questions.']);
        }

        $nextVersion = (int) ($category->questionSets()->max('version') ?? 0) + 1;

        $questionSet = $category->questionSets()->create([
            'version' => $nextVersion,
            'published_at' => now(),
        ]);

        foreach ($questions as $order => $question) {
            $questionSet->questions()->create([
                'key' => $question['key'],
                'label' => $question['label'],
                'type' => $question['type'],
                'required' => $question['required'] ?? false,
                'options' => $question['options'] ?? null,
                'order' => $order,
            ]);
        }

        return $questionSet;
    }
}
