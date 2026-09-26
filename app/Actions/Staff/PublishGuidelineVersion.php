<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Staff\StaffRole;
use App\Models\GuidelineVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * FR-006-01: publishes the next dated version of an audience's guidelines.
 * The previous version is marked not-current but never touched otherwise
 * — every version stays available. Not a moderation/enforcement decision
 * against a user, so this doesn't write to the compliance log (§5.1 keeps
 * that log to staff decisions against a user's content or account).
 */
class PublishGuidelineVersion
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $staff, GuidelineAudience $audience, string $body): GuidelineVersion
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only an Admin can publish guideline versions.');
        }

        return DB::transaction(function () use ($audience, $body) {
            GuidelineVersion::where('audience', $audience)->where('is_current', true)->update(['is_current' => false]);

            $nextVersion = (int) GuidelineVersion::where('audience', $audience)->max('version') + 1;

            return GuidelineVersion::create([
                'audience' => $audience,
                'version' => $nextVersion,
                'body' => $body,
                'published_at' => now(),
                'is_current' => true,
            ]);
        });
    }
}
