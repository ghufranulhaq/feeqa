<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * FR-005-15: one click, no sign-in — the invitation's own signed token
 * (already emailed to the recipient as `{unsubscribe_link}`, FR-005-13) is
 * all that's needed to identify who's unsubscribing and from what.
 * Permanent until the recipient reverses it: there is no expiry column on
 * `invitation_suppressions` by design, and clicking the link twice is a
 * no-op (`firstOrCreate` against the table's own unique constraint), not
 * an error.
 */
class Unsubscribe
{
    /**
     * @throws ModelNotFoundException<ReviewInvitation>
     */
    public function handle(string $token, bool $global = false): ReviewInvitation
    {
        $invitation = ReviewInvitation::where('token', $token)->firstOrFail();

        InvitationSuppression::firstOrCreate([
            'business_id' => $global ? null : $invitation->business_id,
            'recipient_email_hash' => $invitation->recipient_email_hash,
        ], [
            'reason' => 'unsubscribed',
        ]);

        if (! $invitation->status->isTerminal()) {
            $invitation->update(['status' => InvitationStatus::Unsubscribed]);
        }

        return $invitation;
    }
}
