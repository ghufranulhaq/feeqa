<?php

namespace App\Actions\Invitations;

use App\Models\ReviewInvitation;
use Illuminate\Support\Collection;

final class ImportInvitationsFromCsvResult
{
    /**
     * @param  Collection<int, ReviewInvitation>  $created
     * @param  list<array{row: int, email: ?string, reason: string}>  $errors
     */
    public function __construct(
        public readonly Collection $created,
        public readonly array $errors,
        public readonly int $collapsed = 0,
    ) {}
}
