<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\ClaimStatus;
use App\Models\BusinessClaim;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-11(a). Edge cases table: 30-minute code expiry, 5 attempts max.
 */
class VerifyBusinessClaimCode
{
    public function __construct(private readonly CompleteVerifiedBusinessClaim $completer) {}

    public function handle(BusinessClaim $claim, string $code): void
    {
        if ($claim->status !== ClaimStatus::Pending) {
            throw ValidationException::withMessages(['code' => 'This claim is no longer active.']);
        }

        if ($claim->code_expires_at !== null && $claim->code_expires_at->isPast()) {
            $claim->forceFill(['status' => ClaimStatus::Expired])->save();

            throw ValidationException::withMessages(['code' => 'This code has expired.']);
        }

        if ($claim->attempts >= 5) {
            $claim->forceFill(['status' => ClaimStatus::Expired])->save();

            throw ValidationException::withMessages(['code' => 'Too many attempts — start a new claim.']);
        }

        $claim->increment('attempts');

        if (! hash_equals($claim->verification_code ?? '', $code)) {
            if ($claim->attempts >= 5) {
                $claim->forceFill(['status' => ClaimStatus::Expired])->save();
            }

            throw ValidationException::withMessages(['code' => 'That code is incorrect.']);
        }

        $this->completer->handle($claim);
    }
}
