<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\UpdateBusinessLogo;
use App\Actions\Businesses\UpdateBusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Businesses\UpdateBusinessLogoRequest;
use App\Http\Requests\Businesses\UpdateBusinessProfileRequest;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;

class BusinessProfileController extends Controller
{
    public function update(UpdateBusinessProfileRequest $request, Business $business, UpdateBusinessProfile $action): RedirectResponse
    {
        $result = $action->handle($business, $request->user(), $request->validated());

        return back()->with('status', $result->changeRequest !== null
            ? 'Some changes need staff approval before they go live.'
            : 'Profile updated.');
    }

    public function updateLogo(UpdateBusinessLogoRequest $request, Business $business, UpdateBusinessLogo $action): RedirectResponse
    {
        $action->handle($business, $request->file('logo'));

        return back()->with('status', 'Logo updated.');
    }
}
