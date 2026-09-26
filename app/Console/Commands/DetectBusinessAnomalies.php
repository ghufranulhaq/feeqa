<?php

namespace App\Console\Commands;

use App\Actions\Staff\DetectBusinessAnomalies as DetectBusinessAnomaliesAction;
use App\Models\Business;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-006-06: "run at least hourly." Only a Business with a review in the
 * last day can possibly trigger any of the three checks. Scheduled hourly
 * — see routes/console.php.
 */
#[Signature('moderation:detect-anomalies')]
#[Description('Detect review spikes, rating shifts, and new-account clusters on active businesses')]
class DetectBusinessAnomalies extends Command
{
    public function handle(DetectBusinessAnomaliesAction $detect): int
    {
        $businessIds = Business::query()
            ->whereHas('reviews', fn ($query) => $query->where('created_at', '>=', now()->subDay()))
            ->pluck('id');

        $raised = 0;

        foreach (Business::whereIn('id', $businessIds)->cursor() as $business) {
            $raised += $detect->handle($business)->count();
        }

        $this->info("Raised {$raised} moderation incident(s).");

        return self::SUCCESS;
    }
}
