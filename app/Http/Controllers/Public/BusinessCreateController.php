<?php

namespace App\Http\Controllers\Public;

use App\Actions\Businesses\CreateUnclaimedBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\CreateBusinessRequest;
use App\Models\Category;
use App\Support\Businesses\DuplicateBusinessException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FR-002-08: "An unclaimed profile is created and they go straight into
 * writing a review" (scenario 1) — review submission is spec 003, so
 * today this ends at the new profile page instead.
 */
class BusinessCreateController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('public/business-create', [
            'categories' => Category::orderBy('is_system')->get()->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->localisedName(),
                'is_system' => $category->is_system,
            ]),
            'countries' => config('countries'),
            'duplicate_suggestion' => session('duplicate_suggestion'),
        ]);
    }

    public function store(CreateBusinessRequest $request, CreateUnclaimedBusiness $action): RedirectResponse
    {
        try {
            $business = $action->handle($request->only(['domain', 'name', 'country', 'city', 'category_id']));
        } catch (DuplicateBusinessException $e) {
            return back()->withErrors([
                'duplicate' => 'This looks like a business that\'s already listed.',
            ])->with('duplicate_suggestion', [
                'slug' => $e->existing->slug,
                'name' => $e->existing->name,
            ]);
        }

        return redirect()->route('businesses.show', $business->slug);
    }
}
