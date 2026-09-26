<?php

namespace App\Actions\Invitations;

use App\Models\ReviewInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * FR-005-10: "Opening a link pre-fills the review form (business,
 * location, products, reference)." No review-submission page reads this
 * yet (this spec's own opening note) — the shape below is what one would
 * pre-fill with once it exists. Edge case table: an expired invitation
 * resolves to `expired: true` instead of throwing, so the caller can show
 * "this invitation expired, write an Organic review instead" rather than
 * a generic error.
 */
class ResolveInvitationToken
{
    /**
     * @throws ModelNotFoundException<ReviewInvitation>
     */
    public function handle(string $token): ResolveInvitationTokenResult
    {
        $invitation = ReviewInvitation::where('token', $token)->firstOrFail();

        return new ResolveInvitationTokenResult($invitation, $invitation->isExpired());
    }
}
