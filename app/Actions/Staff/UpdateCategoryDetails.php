<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-002-33: "Senior Moderator may edit question sets, topic lists, and
 * category names/descriptions." An Admin can do this too — creating a
 * category is Admin-only, and it would be an odd gap if they then
 * couldn't rename what they just made.
 */
class UpdateCategoryDetails
{
    /**
     * @param  array{name?: array<string, string>, description?: ?array<string, string>, icon?: ?string}  $data
     *
     * @throws AuthorizationException
     */
    public function handle(Category $category, User $staff, array $data): Category
    {
        if (! in_array($staff->staffRole(), [StaffRole::Admin, StaffRole::SeniorModerator], true)) {
            throw new AuthorizationException('Only an Admin or Senior Moderator can edit a category.');
        }

        $category->update($data);

        return $category;
    }
}
