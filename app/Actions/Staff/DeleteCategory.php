<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-36: "A category that still contains businesses cannot be
 * deleted. Staff must first move or merge it into another category"
 * (App\Actions\Staff\MoveBusinessesToCategory / MergeCategories).
 */
class DeleteCategory
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Category $category, User $staff): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can delete a category.');
        }

        if ($category->is_system) {
            throw ValidationException::withMessages(['category' => 'The system "Other / Uncategorised" category cannot be deleted.']);
        }

        if (Business::where('primary_category_id', $category->id)->exists()) {
            throw ValidationException::withMessages(['category' => 'This category still has businesses in it — move or merge them first.']);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category still has sub-categories — move or merge them first.']);
        }

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'category_deleted',
            reasonCode: 'category_cleanup',
            target: $category,
        );

        $category->delete();
    }
}
