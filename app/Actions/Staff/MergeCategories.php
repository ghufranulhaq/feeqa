<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edge cases table: "Two industries merged: businesses, sub-categories,
 * and question sets move to the surviving one. Question-set answers keep
 * their original version (FR-002-20). The old slug redirects."
 */
class MergeCategories
{
    public function __construct(private readonly MoveBusinessesToCategory $moveBusinesses) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Category $source, Category $target, User $staff): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can merge categories.');
        }

        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'A category cannot be merged into itself.']);
        }

        if ($source->is_system || $target->is_system) {
            throw ValidationException::withMessages(['category' => 'The system "Other / Uncategorised" category cannot be merged.']);
        }

        $oldSlug = $source->slug;

        DB::transaction(function () use ($source, $target, $staff, $oldSlug): void {
            Category::where('parent_id', $source->id)->update(['parent_id' => $target->id]);

            $this->moveBusinesses->handle($source, $target, $staff);

            // FR-002-20: never overwrite a version number — a merged-in
            // question set's answers (spec 003, once it exists) still
            // cite this exact (category, version) pair.
            $nextVersion = (int) ($target->questionSets()->max('version') ?? 0);

            foreach ($source->questionSets()->orderBy('version')->get() as $questionSet) {
                $nextVersion++;
                $questionSet->update(['category_id' => $target->id, 'version' => $nextVersion]);
            }

            $source->delete();

            CategorySlugRedirect::updateOrCreate(
                ['old_slug' => $oldSlug],
                ['category_id' => $target->id],
            );
        });

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'categories_merged',
            reasonCode: "merged {$oldSlug} into {$target->slug}",
            target: $target,
        );
    }
}
