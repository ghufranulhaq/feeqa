<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\BccImportOutcome;
use App\Models\ReviewInvitation;

final class ImportBccEmailResult
{
    public function __construct(
        public readonly BccImportOutcome $outcome,
        public readonly ?ReviewInvitation $invitation = null,
    ) {}
}
