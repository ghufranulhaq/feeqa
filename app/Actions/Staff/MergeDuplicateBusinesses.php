<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use App\Models\ComplianceLogEntry;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edge cases table: "Two unclaimed duplicates discovered later: staff
 * merge them. Reviews move to the surviving profile, the other slug
 * redirects, and scores are recalculated." Scores are spec 008, still
 * not built, so there's nothing to recalculate for that part today —
 * this moves everything that does exist (locations, secondary
 * categories, and, since spec 003, reviews) and leaves the redirect and
 * score recalculation for spec 008 to build on. Reviews must move
 * before `$source->delete()` runs: `reviews.business_id` is
 * `cascadeOnDelete()`, so a source business with reviews still attached
 * would otherwise take them down with it instead of surviving the
 * merge.
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

            // Includes soft-deleted reviews: the `businesses` foreign key
            // cascades at the database level regardless of Eloquent's
            // soft-delete flag, so a trashed review left behind would
            // still be destroyed when $source is deleted below.
            Review::withTrashed()->where('business_id', $source->id)->update(['business_id' => $target->id]);

            // A review of some other business that tagged $source now
            // tags $target instead — unless that other business already
            // *is* $target, in which case retargeting would create a
            // self-tag (FR-003-31), so the tag is dropped instead.
            Review::withTrashed()->where('tagged_business_id', $source->id)
                ->where('business_id', '!=', $target->id)
                ->update(['tagged_business_id' => $target->id]);
            Review::withTrashed()->where('tagged_business_id', $source->id)
                ->where('business_id', $target->id)
                ->update(['tagged_business_id' => null]);

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
