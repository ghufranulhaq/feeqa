<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\CreateStaffAccount;
use App\Domain\Staff\StaffRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAccountController extends Controller
{
    public function store(Request $request, CreateStaffAccount $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(StaffRole::class)],
        ]);

        $action->handle(
            $request->user(),
            $validated['name'],
            $validated['email'],
            StaffRole::from($validated['role']),
        );

        return back()->with('status', 'Staff account created.');
    }
}
