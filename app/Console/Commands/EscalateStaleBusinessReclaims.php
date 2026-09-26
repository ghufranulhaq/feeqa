<?php

namespace App\Console\Commands;

use App\Domain\Businesses\ClaimStatus;
use App\Models\BusinessClaim;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-002-14: "With no response, the request goes to staff review."
 * Scheduled daily — see routes/console.php.
 */
#[Signature('business-claims:escalate-stale-reclaims')]
#[Description('Move a re-claim request to staff review once its Owner response deadline has passed')]
class EscalateStaleBusinessReclaims extends Command
{
    public function handle(): int
    {
        $claims = BusinessClaim::where('status', ClaimStatus::AwaitingOwnerResponse)
            ->where('owner_response_deadline', '<=', now())
            ->get();

        foreach ($claims as $claim) {
            $claim->forceFill(['status' => ClaimStatus::EscalatedToStaff])->save();
        }

        $this->info("Escalated {$claims->count()} re-claim request(s).");

        return self::SUCCESS;
    }
}
