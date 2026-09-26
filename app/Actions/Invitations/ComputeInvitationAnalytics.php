<?php

namespace App\Actions\Invitations;

use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-005-19: "funnel, conversion, by method, by template, over time" for
 * the business dashboard's Analyst-and-above analytics view
 * (InvitationAnalyticsController). Computed live from `review_invitations`
 * — the same "no separate tally to keep in sync" shape CancelInvitation's
 * own docblock describes for the neutrality report (FR-005-18).
 */
class ComputeInvitationAnalytics
{
    private const OVER_TIME_WINDOW_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function handle(Business $business): array
    {
        $invitations = ReviewInvitation::where('business_id', $business->id)
            ->get(['method', 'template_id', 'created_at', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'reviewed_at']);

        return [
            'funnel' => $this->funnel($invitations),
            'conversion' => $this->conversion($invitations),
            'by_method' => $this->byGroup($invitations->groupBy(fn (ReviewInvitation $invitation) => $invitation->method->value)),
            'by_template' => $this->byGroup($invitations->whereNotNull('template_id')->groupBy('template_id')),
            'over_time' => $this->overTime($business),
        ];
    }

    /**
     * @param  Collection<int, ReviewInvitation>  $invitations
     * @return array<string, int>
     */
    private function funnel(Collection $invitations): array
    {
        return [
            'queued' => $invitations->count(),
            'sent' => $invitations->whereNotNull('sent_at')->count(),
            'delivered' => $invitations->whereNotNull('delivered_at')->count(),
            'opened' => $invitations->whereNotNull('opened_at')->count(),
            'clicked' => $invitations->whereNotNull('clicked_at')->count(),
            'reviewed' => $invitations->whereNotNull('reviewed_at')->count(),
        ];
    }

    /**
     * @param  Collection<int, ReviewInvitation>  $invitations
     */
    private function conversion(Collection $invitations): ?float
    {
        $sent = $invitations->whereNotNull('sent_at')->count();

        if ($sent === 0) {
            return null;
        }

        return round($invitations->whereNotNull('reviewed_at')->count() / $sent, 4);
    }

    /**
     * @param  Collection<int|string, Collection<int, ReviewInvitation>>  $groups
     * @return array<int|string, array{total: int, reviewed: int}>
     */
    private function byGroup(Collection $groups): array
    {
        return $groups->map(fn (Collection $group) => [
            'total' => $group->count(),
            'reviewed' => $group->whereNotNull('reviewed_at')->count(),
        ])->all();
    }

    /**
     * @return array<string, int>
     */
    private function overTime(Business $business): array
    {
        $windowStart = now()->subDays(self::OVER_TIME_WINDOW_DAYS)->startOfDay();

        return ReviewInvitation::where('business_id', $business->id)
            ->where('created_at', '>=', $windowStart)
            ->get(['created_at'])
            ->groupBy(fn (ReviewInvitation $invitation) => $invitation->created_at->toDateString())
            ->map(fn (Collection $group) => $group->count())
            ->sortKeys()
            ->all();
    }
}
