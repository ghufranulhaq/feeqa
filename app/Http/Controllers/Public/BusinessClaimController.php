<?php

namespace App\Http\Controllers\Public;

use App\Actions\Businesses\RespondToBusinessReclaim;
use App\Actions\Businesses\StartBusinessClaim;
use App\Actions\Businesses\VerifyBusinessClaimCode;
use App\Actions\Businesses\VerifyBusinessDomainClaim;
use App\Domain\Businesses\ClaimMethod;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-002-11 through FR-002-15. No claim-flow UI exists yet — these
 * endpoints are real and usable today, same situation as spec 001's
 * business invitations.
 */
class BusinessClaimController extends Controller
{
    public function store(Request $request, Business $business, StartBusinessClaim $action): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::enum(ClaimMethod::class)],
            'target' => ['nullable', 'email'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
        ]);

        $claim = $action->handle($business, $request->user(), ClaimMethod::from($validated['method']), [
            'target' => $validated['target'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'documents' => $request->file('documents', []),
        ]);

        return back()->with('status', "Claim started (#{$claim->id}).");
    }

    public function verifyCode(Request $request, BusinessClaim $claim, VerifyBusinessClaimCode $action): RedirectResponse
    {
        $validated = $request->validate(['code' => ['required', 'string']]);

        $action->handle($claim, $validated['code']);

        return back()->with('status', 'Business claimed.');
    }

    public function verifyDomain(BusinessClaim $claim, VerifyBusinessDomainClaim $action): RedirectResponse
    {
        $action->handle($claim);

        return back()->with('status', 'Business claimed.');
    }

    public function respond(Request $request, BusinessClaim $claim, RespondToBusinessReclaim $action): RedirectResponse
    {
        $validated = $request->validate(['approve' => ['required', 'boolean']]);

        $action->handle($claim, $request->user(), $validated['approve']);

        return back()->with('status', $validated['approve'] ? 'Request approved.' : 'Request rejected.');
    }
}
