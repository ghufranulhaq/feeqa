<?php

namespace App\Console\Commands;

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-005-10, FR-005-11: "an invitation past `expires_at` with no terminal
 * status becomes `expired`." A reviewed/cancelled/suppressed/etc.
 * invitation is left alone — it already has its own true outcome.
 * Scheduled daily — see routes/console.php.
 */
#[Signature('review-invitations:expire')]
#[Description('Mark every non-terminal review invitation past its expiry as expired')]
class ExpireReviewInvitations extends Command
{
    public function handle(): int
    {
        $terminal = array_map(
            fn (InvitationStatus $status) => $status->value,
            array_filter(InvitationStatus::cases(), fn (InvitationStatus $status) => $status->isTerminal()),
        );

        $expired = ReviewInvitation::whereNotIn('status', $terminal)
            ->where('expires_at', '<=', now())
            ->update(['status' => InvitationStatus::Expired]);

        $this->info("Expired {$expired} invitation(s).");

        return self::SUCCESS;
    }
}
