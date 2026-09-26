<?php

namespace App\Console\Commands;

use App\Actions\Verification\DeleteExpiredProofFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-004-23: raw proof files and non-attestation extracted fields, 30
 * days after a verification decision. Scheduled daily — see
 * routes/console.php.
 */
#[Signature('verification:delete-expired-proofs')]
#[Description('Delete raw proof files and non-attestation extracted fields 30 days after a verification decision')]
class DeleteExpiredVerificationProofs extends Command
{
    public function handle(DeleteExpiredProofFiles $action): int
    {
        $count = $action->handle();

        $this->info("Cleared proof data for {$count} verification(s).");

        return self::SUCCESS;
    }
}
