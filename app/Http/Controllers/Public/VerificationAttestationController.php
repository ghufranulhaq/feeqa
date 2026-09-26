<?php

namespace App\Http\Controllers\Public;

use App\Drivers\Signing\SigningService;
use App\Http\Controllers\Controller;
use App\Models\VerificationAttestation;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * FR-004-15: anyone can check an attestation's signature and revocation
 * status. Only the safe subset of fields ever reaches the response — the
 * proof fingerprint and the raw JWS never do, since the JWS is only
 * signed (not encrypted) and would hand the fingerprint straight back.
 * An unknown ID never reaches `show()` — implicit model binding 404s
 * first, identically for "never existed" and any other lookup failure.
 */
class VerificationAttestationController extends Controller
{
    public function show(VerificationAttestation $attestation): Response
    {
        $signatureValid = $this->verifySignature($attestation);

        return Inertia::render('public/verification-check', [
            'attestation' => [
                'id' => $attestation->id,
                'method' => $attestation->method->value,
                'experience_month' => $attestation->experience_month->toDateString(),
                'decision_time' => $attestation->decision_time->toIso8601String(),
                'business_name' => $attestation->business->name,
                'signature_valid' => $signatureValid,
                'revoked' => $attestation->isRevoked(),
                'revoked_at' => $attestation->revoked_at?->toDateString(),
                'revoked_reason_code' => $attestation->revoked_reason_code,
            ],
        ]);
    }

    /**
     * FR-004-17: "recorded in a public revocation list (ID + date + reason
     * category)" — nothing else about the review or reviewer.
     */
    public function revocations(): Response
    {
        $revocations = VerificationAttestation::query()
            ->whereNotNull('revoked_at')
            ->orderByDesc('revoked_at')
            ->get()
            ->map(fn (VerificationAttestation $attestation) => [
                'id' => $attestation->id,
                'revoked_at' => $attestation->revoked_at->toDateString(),
                'revoked_reason_code' => $attestation->revoked_reason_code,
            ])
            ->all();

        return Inertia::render('public/verification-revocations', [
            'revocations' => $revocations,
        ]);
    }

    private function verifySignature(VerificationAttestation $attestation): bool
    {
        try {
            App::make(SigningService::class)->verify($attestation->jws);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
