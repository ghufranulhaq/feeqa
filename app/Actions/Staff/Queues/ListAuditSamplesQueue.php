<?php

namespace App\Actions\Staff\Queues;

use App\Models\AuditSample;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-006-20: the weekly audit sample, defaulting to what still needs a
 * staff correct/incorrect verdict — oldest first, so a sample doesn't sit
 * unreviewed past `ComputeRulePrecision`'s trailing window without ever
 * having been looked at.
 */
class ListAuditSamplesQueue
{
    /**
     * @return Collection<int, AuditSample>
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, ?bool $pending = true): Collection
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the audit sample queue.');
        }

        return AuditSample::query()
            ->when($pending !== null, fn ($query) => $pending
                ? $query->whereNull('correct')
                : $query->whereNotNull('correct'))
            ->with('screening.screenable')
            ->oldest('created_at')
            ->get();
    }
}
