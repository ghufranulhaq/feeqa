<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\DisputeEmployeeSizeBand;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSizeBandDisputeController extends Controller
{
    public function store(Request $request, Business $business, DisputeEmployeeSizeBand $action): RedirectResponse
    {
        $validated = $request->validate([
            'evidence' => ['required', 'string', 'max:2000'],
            'proposed_band' => ['nullable', Rule::enum(EmployeeSizeBand::class)],
        ]);

        $action->handle(
            $business,
            $request->user(),
            $validated['evidence'],
            isset($validated['proposed_band']) ? EmployeeSizeBand::from($validated['proposed_band']) : null,
        );

        return back()->with('status', 'Dispute submitted.');
    }
}
