<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\CategoryState;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-28, FR-002-30, FR-002-33. Edge cases table: pausing stops
 * sponsored slots (017) and widgets/badges are unaffected either way —
 * neither exists yet, so there's nothing here to touch; only navigation
 * and rankings hide, and that's just the `launched` flag other pages
 * already filter on.
 */
class PauseIndustry
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Category $industry, User $staff): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can pause an industry.');
        }

        if (! $industry->isIndustry()) {
            throw ValidationException::withMessages(['category' => 'Only a top-level category (an industry) has this lifecycle.']);
        }

        if ($industry->state !== CategoryState::Launched) {
            throw ValidationException::withMessages(['state' => 'Only a launched industry can be paused.']);
        }

        $industry->update(['state' => CategoryState::Paused, 'launched' => false]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'industry_paused',
            reasonCode: 'industry_lifecycle',
            target: $industry,
        );
    }
}
