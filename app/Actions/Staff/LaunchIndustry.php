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
 * FR-002-28, FR-002-30, FR-002-31, FR-002-33. Moves an industry to
 * `launched` from either `draft` or `paused`, gated by the FR-002-31
 * readiness checklist: a blocking issue rejects outright; the warnings
 * are always present and must be acknowledged regardless of what they
 * say. State changes never touch a Business's data (FR-002-30).
 */
class LaunchIndustry
{
    public function __construct(private readonly EvaluateIndustryReadiness $readiness) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Category $industry, User $staff, bool $acknowledgeWarnings = false): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can launch an industry.');
        }

        if (! $industry->isIndustry()) {
            throw ValidationException::withMessages(['category' => 'Only a top-level category (an industry) has this lifecycle.']);
        }

        if ($industry->state === CategoryState::Launched) {
            throw ValidationException::withMessages(['state' => 'This industry is already launched.']);
        }

        $checklist = $this->readiness->handle($industry);

        if ($checklist->isBlocked()) {
            throw ValidationException::withMessages(['checklist' => $checklist->blockingIssues]);
        }

        if (! $acknowledgeWarnings) {
            throw ValidationException::withMessages(['acknowledge_warnings' => $checklist->warnings]);
        }

        $industry->update(['state' => CategoryState::Launched, 'launched' => true]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'industry_launched',
            reasonCode: 'industry_lifecycle',
            target: $industry,
        );
    }
}
