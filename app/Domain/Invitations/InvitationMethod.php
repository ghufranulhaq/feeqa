<?php

namespace App\Domain\Invitations;

use App\Domain\Reviews\SourceLabel;

/**
 * FR-005-01. `Integration` (booking-engine/GDS/e-commerce connectors) is
 * Phase 2 — modelled here only so the data model supports it (spec.md §7
 * decided 2026-09-24), never produced in Phase 1, same relationship
 * `payment_link` has with spec 004.
 */
enum InvitationMethod: string
{
    case Bcc = 'bcc';
    case Integration = 'integration';
    case Api = 'api';
    case Csv = 'csv';
    case Manual = 'manual';
    case Link = 'link';

    /**
     * FR-005-02: the review this invitation produces gets a
     * `transaction_invitation` attestation directly, with no separate
     * consumer confirmation step, because the reference came through a
     * channel the Platform already trusts (a validated BCC/API/integration
     * call) rather than something the business typed by hand.
     */
    public function isTransactionLinked(): bool
    {
        return match ($this) {
            self::Bcc, self::Integration, self::Api => true,
            self::Csv, self::Manual, self::Link => false,
        };
    }

    /**
     * FR-005-03, FR-005-04: what the resulting review's source label is,
     * before FR-004 verification is layered on top.
     */
    public function sourceLabel(): SourceLabel
    {
        return match ($this) {
            self::Link => SourceLabel::Redirected,
            default => SourceLabel::Invited,
        };
    }
}
