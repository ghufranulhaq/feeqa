<?php

namespace App\Console\Commands;

use App\Actions\Invitations\ComputeNeutralityReport;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-005-18: "compute a per-Business neutrality report every day." Only a
 * Business with at least one invitation has anything to report on.
 * Scheduled daily — see routes/console.php.
 */
#[Signature('review-invitations:neutrality-report')]
#[Description('Compute the daily per-Business neutrality report and alert staff on threshold breaches')]
class ComputeInvitationNeutralityReports extends Command
{
    public function handle(ComputeNeutralityReport $compute): int
    {
        $businessIds = ReviewInvitation::distinct()->pluck('business_id');
        $computed = 0;

        foreach (Business::whereIn('id', $businessIds)->cursor() as $business) {
            $compute->handle($business);
            $computed++;
        }

        $this->info("Computed the neutrality report for {$computed} business(es).");

        return self::SUCCESS;
    }
}
