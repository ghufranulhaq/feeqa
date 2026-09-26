<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\GuidelineVersion;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-006-14 step 4: switches one of `RestrictableFeature`'s cases off for
 * a Business. Idempotent — restricting an already-restricted feature is a
 * no-op rather than an error, since the ladder can reach this step more
 * than once.
 */
class RestrictBusinessFeature
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $staff, Business $business, RestrictableFeature $feature, ReasonCode $reasonCode): Business
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can restrict a business feature.');
        }

        if (! $business->hasFeatureRestricted($feature)) {
            $restricted = $business->restricted_features ?? [];
            $restricted[] = $feature->value;
            $business->update(['restricted_features' => $restricted]);
        }

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'business_feature_restricted',
            reasonCode: $reasonCode->value,
            target: $business,
        );

        $guidelineVersion = GuidelineVersion::query()
            ->where('audience', GuidelineAudience::Business)
            ->where('is_current', true)
            ->value('version');

        foreach ($business->owners() as $owner) {
            $owner->notify(new StatementOfReasonsNotification(
                whatWasAffected: "your business's {$feature->value} feature",
                reasonCode: $reasonCode,
                guidelineVersion: $guidelineVersion,
                automated: false,
            ));
        }

        return $business->fresh();
    }
}
