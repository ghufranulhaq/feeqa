<?php

namespace App\Actions\Staff\Queues;

use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Models\Flag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-006-11: the flags queue, filterable by status, reason code
 * (category), and assignment — defaults to unresolved flags (open or
 * blurred), ordered by SLA due date so the most urgent (24h harmful/
 * personal-info) surface first.
 */
class ListFlagsQueue
{
    /**
     * @return Collection<int, Flag>
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, ?FlagStatus $status = null, ?ReasonCode $reasonCode = null, ?int $assignedTo = null): Collection
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the flags queue.');
        }

        return Flag::query()
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [FlagStatus::Open, FlagStatus::Blurred]),
            )
            ->when($reasonCode !== null, fn ($query) => $query->where('reason_code', $reasonCode))
            ->when($assignedTo !== null, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->with('flaggable')
            ->orderBy('sla_due_at')
            ->get();
    }
}
