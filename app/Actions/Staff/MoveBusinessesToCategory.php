<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\Category;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * FR-002-36: moving businesses out of a category (so it can then be
 * deleted, or just to reorganise) "re-runs rankings and benchmarks but
 * never changes Review Scores or Trust Indexes" — neither exists yet
 * (specs 008/009), so there is nothing to recompute; this only ever
 * touches primary_category_id / the secondary-category pivot.
 */
class MoveBusinessesToCategory
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Category $from, Category $to, User $staff): int
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can move businesses between categories.');
        }

        $moved = Business::where('primary_category_id', $from->id)->update(['primary_category_id' => $to->id]);

        DB::table('business_categories')
            ->where('category_id', $from->id)
            ->whereNotIn('business_id', DB::table('business_categories')->where('category_id', $to->id)->pluck('business_id'))
            ->update(['category_id' => $to->id]);

        DB::table('business_categories')->where('category_id', $from->id)->delete();

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'businesses_moved_between_categories',
            reasonCode: 'category_reorganisation',
            target: $to,
        );

        return $moved;
    }
}
