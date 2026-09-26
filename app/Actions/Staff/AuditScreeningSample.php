<?php

namespace App\Actions\Staff;

use App\Domain\Reviews\ReviewStatus;
use App\Models\AuditSample;
use App\Models\Screening;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FR-006-20: "at least 2% of automated decisions (publish and reject)
 * must be randomly sampled every week." A `hold` recommendation isn't an
 * automated *decision* — it's a referral to a human, who already reviews
 * it — so only `publish`/`reject` screenings are eligible. Rows already
 * sampled (`screenings.id` unique on `audit_samples`) are skipped rather
 * than re-picked, via `firstOrCreate`.
 */
class AuditScreeningSample
{
    private const MINIMUM_SAMPLE_RATE = 0.02;

    /**
     * @return Collection<int, AuditSample>
     */
    public function handle(?Carbon $asOf = null): Collection
    {
        $now = ($asOf ?? now())->copy();
        $windowStart = $now->copy()->subWeek();

        $eligibleIds = Screening::query()
            ->whereIn('recommendation', [ReviewStatus::Published, ReviewStatus::Rejected])
            ->whereBetween('created_at', [$windowStart, $now])
            ->pluck('id');

        if ($eligibleIds->isEmpty()) {
            return collect();
        }

        $sampleSize = max(1, (int) ceil($eligibleIds->count() * self::MINIMUM_SAMPLE_RATE));

        return $eligibleIds->shuffle()
            ->take($sampleSize)
            ->map(fn (int $screeningId) => AuditSample::query()->firstOrCreate(['screening_id' => $screeningId]))
            ->values();
    }
}
