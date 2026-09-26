<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\CreateCategory;
use App\Actions\Staff\DeleteCategory;
use App\Actions\Staff\LaunchIndustry;
use App\Actions\Staff\MergeCategories;
use App\Actions\Staff\MoveBusinessesToCategory;
use App\Actions\Staff\PauseIndustry;
use App\Actions\Staff\PreviewIndustry;
use App\Actions\Staff\RenameCategorySlug;
use App\Actions\Staff\SetCategoryLaunched;
use App\Actions\Staff\UpdateCategoryDetails;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-002-28 through FR-002-33. No staff console UI reaches this yet —
 * same "real endpoint, not linked from anywhere" situation as the rest
 * of spec 002.
 */
class CategoryController extends Controller
{
    public function store(Request $request, CreateCategory $action): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', Rule::exists(Category::class, 'id')],
            'slug' => ['required', 'string', 'max:255'],
            'name' => ['required', 'array'],
            'icon' => ['nullable', 'string', 'max:255'],
        ]);

        $parent = isset($validated['parent_id']) ? Category::findOrFail($validated['parent_id']) : null;

        $action->handle($request->user(), $parent, $validated['slug'], $validated['name'], $validated['icon'] ?? null);

        return back()->with('status', 'Category created.');
    }

    public function update(Request $request, Category $category, UpdateCategoryDetails $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'array'],
            'description' => ['sometimes', 'nullable', 'array'],
        ]);

        $action->handle($category, $request->user(), $validated);

        return back()->with('status', 'Category updated.');
    }

    public function launch(Request $request, Category $industry, LaunchIndustry $action): RedirectResponse
    {
        $validated = $request->validate(['acknowledge_warnings' => ['sometimes', 'boolean']]);

        $action->handle($industry, $request->user(), $validated['acknowledge_warnings'] ?? false);

        return back()->with('status', 'Industry launched.');
    }

    public function preview(Request $request, Category $industry, PreviewIndustry $action): array
    {
        return $action->handle($industry, $request->user());
    }

    public function pause(Request $request, Category $industry, PauseIndustry $action): RedirectResponse
    {
        $action->handle($industry, $request->user());

        return back()->with('status', 'Industry paused.');
    }

    public function setLaunched(Request $request, Category $category, SetCategoryLaunched $action): RedirectResponse
    {
        $validated = $request->validate(['launched' => ['required', 'boolean']]);

        $action->handle($category, $request->user(), $validated['launched']);

        return back()->with('status', 'Category updated.');
    }

    public function renameSlug(Request $request, Category $category, RenameCategorySlug $action): RedirectResponse
    {
        $validated = $request->validate(['slug' => ['required', 'string', 'max:255']]);

        $action->handle($category, $validated['slug'], $request->user());

        return back()->with('status', 'Category slug updated.');
    }

    public function moveBusinesses(Request $request, Category $category, MoveBusinessesToCategory $action): RedirectResponse
    {
        $validated = $request->validate(['to' => ['required', Rule::exists(Category::class, 'id')]]);

        $action->handle($category, Category::findOrFail($validated['to']), $request->user());

        return back()->with('status', 'Businesses moved.');
    }

    public function merge(Request $request, Category $category, MergeCategories $action): RedirectResponse
    {
        $validated = $request->validate(['target' => ['required', Rule::exists(Category::class, 'id')]]);

        $action->handle($category, Category::findOrFail($validated['target']), $request->user());

        return back()->with('status', 'Categories merged.');
    }

    public function destroy(Request $request, Category $category, DeleteCategory $action): RedirectResponse
    {
        $action->handle($category, $request->user());

        return back()->with('status', 'Category deleted.');
    }
}
