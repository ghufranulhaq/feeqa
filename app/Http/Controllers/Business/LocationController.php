<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\CreateBusinessLocation;
use App\Actions\Businesses\DeleteBusinessLocation;
use App\Actions\Businesses\UpdateBusinessLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\StoreLocationRequest;
use App\Http\Requests\Businesses\UpdateLocationRequest;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-002-16. No dashboard UI reaches this yet — same "real endpoint, not
 * linked from anywhere" situation as the rest of spec 002 so far.
 */
class LocationController extends Controller
{
    public function store(StoreLocationRequest $request, Business $business, CreateBusinessLocation $action): RedirectResponse
    {
        $action->handle($business, $request->user(), $request->validated());

        return back()->with('status', 'Location added.');
    }

    public function update(UpdateLocationRequest $request, Business $business, Location $location, UpdateBusinessLocation $action): RedirectResponse
    {
        $action->handle($location, $request->user(), $request->validated());

        return back()->with('status', 'Location updated.');
    }

    public function destroy(Request $request, Business $business, Location $location, DeleteBusinessLocation $action): RedirectResponse
    {
        $action->handle($location, $request->user());

        return back()->with('status', 'Location removed.');
    }
}
