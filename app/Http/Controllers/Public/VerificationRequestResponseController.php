<?php

namespace App\Http\Controllers\Public;

use App\Actions\Verification\RespondToVerificationRequest;
use App\Domain\Verification\ConsumerVerificationResponse;
use App\Http\Controllers\Controller;
use App\Models\BusinessVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VerificationRequestResponseController extends Controller
{
    public function store(Request $request, BusinessVerificationRequest $verificationRequest, RespondToVerificationRequest $action): RedirectResponse
    {
        $validated = $request->validate([
            'response' => ['required', Rule::enum(ConsumerVerificationResponse::class)],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle(
            $request->user(),
            $verificationRequest,
            ConsumerVerificationResponse::from($validated['response']),
            $validated['reference'] ?? null,
        );

        return back()->with('status', 'Response submitted.');
    }
}
