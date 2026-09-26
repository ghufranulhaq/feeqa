<?php

namespace App\Domain\Verification;

/**
 * FR-004-01. `PaymentLink` is Phase 2 — specified here only so the data
 * model supports it (spec.md §5), never issued in Phase 1.
 */
enum VerificationMethod: string
{
    case TransactionInvitation = 'transaction_invitation';
    case ReferenceMatch = 'reference_match';
    case DocumentProof = 'document_proof';
    case PaymentLink = 'payment_link';
}
