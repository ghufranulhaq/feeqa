<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\AppealStatus;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Appeal;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use App\Models\Screening;
use App\Models\TransparencyReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FR-006-21(e), FR-006-22: every number here is read straight from the
 * compliance log, flags, appeals, screenings, or the enforcement ledger
 * for the given period — nothing is stored that doesn't reconcile back
 * to one of those tables, and running this twice for the same period
 * (`updateOrCreate` by [period_start, period_end]) always reproduces the
 * same figures from the same underlying rows.
 *
 * The one figure with no source table — government/legal requests — is a
 * manually-entered count (`$legalRequestsCount`), an honest gap rather
 * than inventing a request-tracking feature this spec never asked for.
 *
 * "Submitted"/"published" are the cohort of reviews created in the
 * period; "removed by reason" instead counts staff *actions taken*
 * during the period (from the compliance log), regardless of when the
 * removed review was originally submitted — the natural reading of a
 * quarterly *activity* report, and the only one a compliance-log-sourced
 * figure can honestly give.
 */
class ComputeTransparencyReport
{
    public function handle(Carbon $periodStart, Carbon $periodEnd, int $legalRequestsCount = 0): TransparencyReport
    {
        $figures = [
            'reviews' => $this->reviewFigures($periodStart, $periodEnd),
            'detection' => $this->detectionFigures($periodStart, $periodEnd),
            'flags' => $this->flagFigures($periodStart, $periodEnd),
            'appeals' => $this->appealFigures($periodStart, $periodEnd),
            'consumer_warnings_issued' => EnforcementAction::query()
                ->where('step', EnforcementStep::ConsumerWarning)
                ->whereBetween('applied_at', [$periodStart, $periodEnd])
                ->count(),
            'accounts_blocked' => EnforcementAction::query()
                ->where('step', EnforcementStep::AccountBlock)
                ->whereBetween('applied_at', [$periodStart, $periodEnd])
                ->count(),
            'legal_requests' => $legalRequestsCount,
        ];

        return TransparencyReport::query()->updateOrCreate(
            ['period_start' => $periodStart->toDateString(), 'period_end' => $periodEnd->toDateString()],
            ['figures' => $figures, 'generated_at' => now()],
        );
    }

    /**
     * @return array{submitted: int, published: int, removed_by_reason: array<string, int>}
     */
    private function reviewFigures(Carbon $start, Carbon $end): array
    {
        return [
            'submitted' => Review::query()->whereBetween('created_at', [$start, $end])->count(),
            'published' => Review::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', ReviewStatus::Published)
                ->count(),
            'removed_by_reason' => ComplianceLogEntry::query()
                ->whereIn('action', ['review_remove', 'review_mark_not_genuine'])
                ->whereBetween('occurred_at', [$start, $end])
                ->selectRaw('reason_code, count(*) as aggregate')
                ->groupBy('reason_code')
                ->pluck('aggregate', 'reason_code')
                ->all(),
        ];
    }

    /**
     * @return array{automated: int, flagged: int, automated_percentage: float|null}
     */
    private function detectionFigures(Carbon $start, Carbon $end): array
    {
        $automated = Screening::query()
            ->where('recommendation', ReviewStatus::Rejected)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $flagged = Flag::query()
            ->where('status', FlagStatus::Upheld)
            ->whereBetween('decided_at', [$start, $end])
            ->count();

        $total = $automated + $flagged;

        return [
            'automated' => $automated,
            'flagged' => $flagged,
            'automated_percentage' => $total > 0 ? round($automated / $total * 100, 1) : null,
        ];
    }

    /**
     * @return array{received_by_reporter_type: array{guest: int, reviewer: int, business: int}, handled_by_reporter_type: array{guest: int, reviewer: int, business: int}, median_hours_to_action: float|null}
     */
    private function flagFigures(Carbon $start, Carbon $end): array
    {
        $received = Flag::query()->whereBetween('created_at', [$start, $end])->get();
        $handled = Flag::query()
            ->whereIn('status', [FlagStatus::Upheld, FlagStatus::Rejected])
            ->whereBetween('decided_at', [$start, $end])
            ->get();

        return [
            'received_by_reporter_type' => $this->countByReporterType($received),
            'handled_by_reporter_type' => $this->countByReporterType($handled),
            'median_hours_to_action' => $this->medianHoursToAction($handled),
        ];
    }

    /**
     * @param  Collection<int, Flag>  $flags
     * @return array{guest: int, reviewer: int, business: int}
     */
    private function countByReporterType(Collection $flags): array
    {
        $counts = ['guest' => 0, 'reviewer' => 0, 'business' => 0];

        foreach ($flags as $flag) {
            $type = match (true) {
                $flag->is_business_flag => 'business',
                $flag->reporter_id !== null => 'reviewer',
                default => 'guest',
            };
            $counts[$type]++;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, Flag>  $flags
     */
    private function medianHoursToAction(Collection $flags): ?float
    {
        if ($flags->isEmpty()) {
            return null;
        }

        $hours = $flags
            ->map(fn (Flag $flag) => $flag->created_at->diffInMinutes($flag->decided_at, absolute: true) / 60)
            ->sort()
            ->values();

        $count = $hours->count();
        $middle = intdiv($count, 2);

        $median = $count % 2 === 0
            ? ($hours[$middle - 1] + $hours[$middle]) / 2
            : $hours[$middle];

        return round($median, 1);
    }

    /**
     * @return array{decided: int, overturned: int, overturn_rate: float|null}
     */
    private function appealFigures(Carbon $start, Carbon $end): array
    {
        $decided = Appeal::query()
            ->whereIn('status', [AppealStatus::Upheld, AppealStatus::Overturned])
            ->whereBetween('decided_at', [$start, $end])
            ->get();

        $overturned = $decided->where('status', AppealStatus::Overturned)->count();

        return [
            'decided' => $decided->count(),
            'overturned' => $overturned,
            'overturn_rate' => $decided->count() > 0 ? round($overturned / $decided->count() * 100, 1) : null,
        ];
    }
}
