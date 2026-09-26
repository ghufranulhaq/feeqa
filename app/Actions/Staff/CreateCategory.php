<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\CategoryState;
use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-28, FR-002-33: staff Admin only. A new top-level category (no
 * parent) is an industry and starts `draft`; anything else just gets the
 * plain FR-002-18 `launched = false`.
 */
class CreateCategory
{
    /**
     * @param  array<string, string>  $name
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, ?Category $parent, string $slug, array $name, ?string $icon = null): Category
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can create a category.');
        }

        if (Category::depthUnder($parent) > Category::MAX_DEPTH) {
            throw ValidationException::withMessages(['parent_id' => 'Categories may only be nested 3 levels deep.']);
        }

        if (Category::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'That slug is already in use.']);
        }

        return Category::create([
            'parent_id' => $parent?->id,
            'slug' => $slug,
            'name' => $name,
            'icon' => $icon,
            'launched' => false,
            'state' => $parent === null ? CategoryState::Draft : null,
        ]);
    }
}
