<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\ResolveEmployeeSizeBandDispute;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Http\Controllers\Controller;
use App\Models\EmployeeSizeBandDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSizeBandDisputeController extends Controller
{
    public function resolve(Request $request, EmployeeSizeBandDispute $dispute, ResolveEmployeeSizeBandDispute $action): RedirectResponse
    {
        $validated = $request->validate([
            'change_band' => ['required', 'boolean'],
            'new_band' => ['required_if:change_band,true', Rule::enum(EmployeeSizeBand::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $action->handle(
            $dispute,
            $request->user(),
            $validated['change_band'],
            isset($validated['new_band']) ? EmployeeSizeBand::from($validated['new_band']) : null,
            $validated['notes'] ?? null,
        );

        return back()->with('status', 'Dispute resolved.');
    }
}
