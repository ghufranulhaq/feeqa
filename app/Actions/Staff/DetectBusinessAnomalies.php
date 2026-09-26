<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\IncidentStatus;
use App\Domain\Moderation\IncidentType;
use App\Models\Business;
use App\Models\ModerationIncident;
use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * FR-006-06: run at least hourly (routes/console.php) across every
 * Business with recent review activity. Each check is independent — a
 * Business can have all three incident types open at once.
 */
class DetectBusinessAnomalies
{
    public function handle(Business $business): Collection
    {
        $incidents = collect();

        if (($incident = $this->detectReviewSpike($business)) !== null) {
            $incidents->push($incident);
        }

        if (($incident = $this->detectRatingShift($business)) !== null) {
            $incidents->push($incident);
        }

        if (($incident = $this->detectNewAccountCluster($business)) !== null) {
            $incidents->push($incident);
        }

        return $incidents;
    }

    /**
     * FR-006-06: "> 5x the 30-day daily median with >= 20 reviews".
     */
    private function detectReviewSpike(Business $business): ?ModerationIncident
    {
        $todayCount = $business->reviews()->where('created_at', '>=', now()->subDay())->count();
        $minReviews = (int) config('platform.moderation.anomalies.review_spike_min_reviews', 20);

        if ($todayCount < $minReviews) {
            return null;
        }

        $dailyMedian = $this->dailyMedianOverTrailingWindow($business);
        $multiplier = (float) config('platform.moderation.anomalies.review_spike_multiplier', 5);

        if ($dailyMedian <= 0.0 || $todayCount <= $dailyMedian * $multiplier) {
            return null;
        }

        return $this->recordIncident($business, IncidentType::ReviewSpike, [
            'today_count' => $todayCount,
            'thirty_day_daily_median' => $dailyMedian,
        ]);
    }

    /**
     * Documented threshold (config): today's average star rating vs. the
     * prior 30 days', with a minimum sample on each side.
     */
    private function detectRatingShift(Business $business): ?ModerationIncident
    {
        $minReviews = (int) config('platform.moderation.anomalies.rating_shift_min_reviews', 10);

        $today = $business->reviews()->where('created_at', '>=', now()->subDay());
        $todayCount = (clone $today)->count();

        if ($todayCount < $minReviews) {
            return null;
        }

        $todayAverage = (float) (clone $today)->avg('star_rating');

        $prior = $business->reviews()
            ->where('created_at', '<', now()->subDay())
            ->where('created_at', '>=', now()->subDays(31));
        $priorCount = (clone $prior)->count();

        if ($priorCount < $minReviews) {
            return null;
        }

        $priorAverage = (float) (clone $prior)->avg('star_rating');
        $threshold = (float) config('platform.moderation.anomalies.rating_shift_threshold', 1.5);

        if (abs($todayAverage - $priorAverage) < $threshold) {
            return null;
        }

        return $this->recordIncident($business, IncidentType::RatingShift, [
            'today_average' => round($todayAverage, 2),
            'today_count' => $todayCount,
            'prior_30_day_average' => round($priorAverage, 2),
            'prior_30_day_count' => $priorCount,
        ]);
    }

    /**
     * FR-006-06: "clusters of new accounts."
     */
    private function detectNewAccountCluster(Business $business): ?ModerationIncident
    {
        $ageDays = (int) config('platform.moderation.anomalies.new_account_cluster_account_age_days', 7);
        $minReviews = (int) config('platform.moderation.anomalies.new_account_cluster_min_reviews', 5);

        $count = $business->reviews()
            ->where('created_at', '>=', now()->subDay())
            ->whereHas('reviewer', fn ($query) => $query->where('created_at', '>=', now()->subDays($ageDays)))
            ->count();

        if ($count < $minReviews) {
            return null;
        }

        return $this->recordIncident($business, IncidentType::NewAccountCluster, [
            'new_account_reviews_last_24h' => $count,
            'account_age_threshold_days' => $ageDays,
        ]);
    }

    private function dailyMedianOverTrailingWindow(Business $business): float
    {
        $windowDays = 30;

        $counts = $business->reviews()
            ->where('created_at', '>=', now()->subDays($windowDays + 1))
            ->where('created_at', '<', now()->subDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total')
            ->map(fn ($total) => (int) $total)
            ->sort()
            ->values();

        if ($counts->isEmpty()) {
            return 0.0;
        }

        $middle = intdiv($counts->count(), 2);

        if ($counts->count() % 2 === 0) {
            return ($counts[$middle - 1] + $counts[$middle]) / 2;
        }

        return (float) $counts[$middle];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private function recordIncident(Business $business, IncidentType $type, array $metrics): ?ModerationIncident
    {
        $alreadyOpen = ModerationIncident::query()
            ->where('business_id', $business->id)
            ->where('type', $type)
            ->whereIn('status', [IncidentStatus::Open, IncidentStatus::Investigating])
            ->exists();

        if ($alreadyOpen) {
            return null;
        }

        return ModerationIncident::create([
            'business_id' => $business->id,
            'type' => $type,
            'status' => IncidentStatus::Open,
            'detected_at' => now(),
            'metrics' => $metrics,
        ]);
    }
}
