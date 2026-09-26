<?php

namespace App\Jobs;

use App\Domain\Businesses\BusinessStatus;
use App\Drivers\BusinessListing\Contracts\BusinessListingChecker;
use App\Models\Business;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * FR-002-10: runs once against a newly-created unclaimed Business. A pass
 * moves it out of `pending` (so a later spec's search can show it); a
 * failure leaves it `pending` with a reason recorded for staff to review —
 * nothing here blocks the business from being reviewed in the meantime.
 */
class RunBusinessListingCheck implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $businessId) {}

    public function handle(BusinessListingChecker $checker): void
    {
        $business = Business::findOrFail($this->businessId);
        $result = $checker->check($business);

        $business->forceFill([
            'listing_checked_at' => now(),
            'listing_check_notes' => $result->reason,
            'status' => $result->approved ? BusinessStatus::Unclaimed : BusinessStatus::Pending,
        ])->save();
    }
}
