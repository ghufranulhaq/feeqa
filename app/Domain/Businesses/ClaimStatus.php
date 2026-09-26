<?php

namespace App\Domain\Businesses;

/**
 * FR-002-13, FR-002-14. `AwaitingOwnerResponse` and `EscalatedToStaff`
 * only happen for a re-claim on an already-claimed business; every other
 * method/state goes straight from Pending to Approved/Rejected/Expired.
 */
enum ClaimStatus: string
{
    case Pending = 'pending';
    case AwaitingOwnerResponse = 'awaiting_owner_response';
    case EscalatedToStaff = 'escalated_to_staff';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
