<?php

namespace App\Actions\Staff;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-002-32: "Staff must be able to preview an industry's public pages
 * ... before launching." Read-only, so every staff role can use it
 * (FR-002-33: "other staff roles have read-only access"). Rankings are
 * spec 009, not built yet — that section is honestly `null` rather than
 * faked.
 */
class PreviewIndustry
{
    /**
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function handle(Category $industry, User $staff): array
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can preview an industry.');
        }

        return [
            'navigation_entry' => [
                'slug' => $industry->slug,
                'name' => $industry->localisedName(),
                'icon' => $industry->icon,
            ],
            'category_page' => [
                'name' => $industry->localisedName(),
                'description' => $industry->description['en-GB'] ?? null,
                'sub_categories' => $industry->children->map(fn (Category $child) => [
                    'slug' => $child->slug,
                    'name' => $child->localisedName(),
                ])->values()->all(),
                'business_count' => Business::where('primary_category_id', $industry->id)->count(),
            ],
            'ranking' => null,
            'review_form' => [
                'questions' => $industry->effectiveQuestions()->map(fn ($question) => [
                    'key' => $question->key,
                    'label' => $question->localisedLabel(),
                    'type' => $question->type->value,
                    'required' => $question->required,
                ])->values()->all(),
            ],
        ];
    }
}
