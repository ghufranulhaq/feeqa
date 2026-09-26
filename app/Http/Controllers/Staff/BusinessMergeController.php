<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\MergeDuplicateBusinesses;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessMergeController extends Controller
{
    public function store(Request $request, Business $business, MergeDuplicateBusinesses $action): RedirectResponse
    {
        $validated = $request->validate(['target' => ['required', Rule::exists(Business::class, 'id')]]);

        $action->handle($business, Business::findOrFail($validated['target']), $request->user());

        return back()->with('status', 'Businesses merged.');
    }
}
