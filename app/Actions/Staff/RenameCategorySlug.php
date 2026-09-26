<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Edge cases table: "A category is renamed: its slug stays the same, or
 * the old slug permanently redirects if staff change it." Mirrors
 * App\Actions\Businesses\RenameBusinessSlug — the one place a category's
 * slug is ever changed, so future callers (a merge, T12) can't bypass it.
 */
class RenameCategorySlug
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Category $category, string $newSlug, User $staff): void
    {
        if ($staff->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can change a category\'s slug.');
        }

        if ($newSlug === $category->slug) {
            return;
        }

        if (Category::where('slug', $newSlug)->exists()) {
            throw new InvalidArgumentException("The slug \"{$newSlug}\" is already in use.");
        }

        DB::transaction(function () use ($category, $newSlug): void {
            CategorySlugRedirect::updateOrCreate(
                ['old_slug' => $category->slug],
                ['category_id' => $category->id],
            );

            $category->update(['slug' => $newSlug]);

            CategorySlugRedirect::where('old_slug', $newSlug)->delete();
        });
    }
}
