<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FR-002-02: whenever a Business's slug changes, for any reason (a staff
 * rename, a merge — 012 T12, etc.), the old slug keeps working as a
 * permanent redirect. This is the one place a slug is ever changed, so
 * that guarantee can't be bypassed.
 */
class RenameBusinessSlug
{
    public function handle(Business $business, string $newSlug): void
    {
        if ($newSlug === $business->slug) {
            return;
        }

        if (Business::where('slug', $newSlug)->exists()) {
            throw new InvalidArgumentException("The slug \"{$newSlug}\" is already in use.");
        }

        DB::transaction(function () use ($business, $newSlug): void {
            BusinessSlugRedirect::updateOrCreate(
                ['old_slug' => $business->slug],
                ['business_id' => $business->id],
            );

            $business->update(['slug' => $newSlug]);

            // The new slug might itself have been someone's old slug in
            // the past (e.g. a slug freed up and reused) — that record
            // would now point somewhere wrong, so drop it.
            BusinessSlugRedirect::where('old_slug', $newSlug)->delete();
        });
    }
}
