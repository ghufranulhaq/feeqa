<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use App\Models\ComplianceLogEntry;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edge cases table: "Two unclaimed duplicates discovered later: staff
 * merge them. Reviews move to the surviving profile, the other slug
 * redirects, and scores are recalculated." Reviews and scores are specs
 * 003/008 — not built yet, so there's nothing to move or recalculate for
 * those parts today; this moves what does exist (locations, secondary
 * categories) and leaves the redirect for spec 003/008 to build on.
 */
class MergeDuplicateBusinesses
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $source, Business $target, User $staff): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can merge duplicate businesses.');
        }

        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target' => 'A business cannot be merged into itself.']);
        }

        $oldSlug = $source->slug;

        DB::transaction(function () use ($source, $target, $oldSlug): void {
            Location::where('business_id', $source->id)->update(['business_id' => $target->id]);

            DB::table('business_categories')
                ->where('business_id', $source->id)
                ->whereNotIn('category_id', DB::table('business_categories')->where('business_id', $target->id)->pluck('category_id'))
                ->update(['business_id' => $target->id]);
            DB::table('business_categories')->where('business_id', $source->id)->delete();

            $source->delete();

            BusinessSlugRedirect::updateOrCreate(
                ['old_slug' => $oldSlug],
                ['business_id' => $target->id],
            );
        });

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'businesses_merged',
            reasonCode: "merged {$oldSlug} into {$target->slug}",
            target: $target,
        );
    }
}
