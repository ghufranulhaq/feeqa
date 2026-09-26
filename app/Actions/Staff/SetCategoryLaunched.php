<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-18/FR-002-22, FR-002-33: the plain launched/not flag for a
 * non-top-level category (an industry uses the richer LaunchIndustry/
 * PauseIndustry lifecycle instead).
 */
class SetCategoryLaunched
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Category $category, User $staff, bool $launched): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can launch or hide a category.');
        }

        if ($category->isIndustry()) {
            throw ValidationException::withMessages(['category' => 'An industry uses launch/pause instead.']);
        }

        $category->update(['launched' => $launched]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: $launched ? 'category_launched' : 'category_hidden',
            reasonCode: 'category_lifecycle',
            target: $category,
        );
    }
}
