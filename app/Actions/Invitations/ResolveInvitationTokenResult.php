<?php

namespace App\Actions\Invitations;

use App\Models\ReviewInvitation;

final class ResolveInvitationTokenResult
{
    public function __construct(
        public readonly ReviewInvitation $invitation,
        public readonly bool $expired,
    ) {}
}
