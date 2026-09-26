<?php

namespace App\Domain\Invitations;

/**
 * FR-005-11. `Queued` is the only status that isn't terminal and isn't
 * "in flight" — it's also where a `plan_limit`-held invitation
 * (FR-005-20) sits until it's released.
 */
enum InvitationStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Reviewed = 'reviewed';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';
    case Suppressed = 'suppressed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Reviewed, self::Bounced, self::Complained, self::Unsubscribed,
            self::Suppressed, self::Cancelled, self::Expired => true,
            default => false,
        };
    }
}
