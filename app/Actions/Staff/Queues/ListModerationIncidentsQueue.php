<?php

namespace App\Actions\Staff\Queues;

use App\Domain\Moderation\IncidentStatus;
use App\Models\ModerationIncident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-006-06, FR-006-11: the business-incidents queue — defaults to
 * incidents still needing staff attention (open or investigating),
 * detected-earliest first.
 */
class ListModerationIncidentsQueue
{
    /**
     * @return Collection<int, ModerationIncident>
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, ?IncidentStatus $status = null, ?int $assignedTo = null): Collection
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the incidents queue.');
        }

        return ModerationIncident::query()
            ->when(
                $status !== null,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->whereIn('status', [IncidentStatus::Open, IncidentStatus::Investigating]),
            )
            ->when($assignedTo !== null, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->with('business')
            ->oldest('detected_at')
            ->get();
    }
}
