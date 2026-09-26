<?php

namespace App\Http\Controllers\Public;

use App\Actions\Businesses\CloseBusiness;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Edge cases table: reachable by an Owner (its own business) or any
 * staff member (any business) — the action itself decides which,
 * so one endpoint covers both.
 */
class BusinessClosureController extends Controller
{
    public function store(Request $request, Business $business, CloseBusiness $action): RedirectResponse
    {
        $action->handle($business, $request->user());

        return back()->with('status', 'Business closed.');
    }
}
