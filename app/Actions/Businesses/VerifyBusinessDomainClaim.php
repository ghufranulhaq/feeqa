<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\ClaimStatus;
use App\Drivers\BusinessClaiming\Contracts\DomainClaimChecker;
use App\Models\BusinessClaim;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-11(b)/(c).
 */
class VerifyBusinessDomainClaim
{
    public function __construct(
        private readonly DomainClaimChecker $checker,
        private readonly CompleteVerifiedBusinessClaim $completer,
    ) {}

    public function handle(BusinessClaim $claim): void
    {
        if ($claim->status !== ClaimStatus::Pending) {
            throw ValidationException::withMessages(['claim' => 'This claim is no longer active.']);
        }

        if (! $this->checker->check($claim)) {
            throw ValidationException::withMessages(['claim' => 'Verification not found yet — check the record/file is in place and try again.']);
        }

        $this->completer->handle($claim);
    }
}
