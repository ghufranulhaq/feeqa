<?php

namespace App\Actions\Staff;

use App\Models\Flag;
use App\Models\ModerationIncident;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-11: "items can be assigned" — one action for every queue item
 * type that carries an `assigned_to` column (`Review`, `Flag`,
 * `ModerationIncident`), rather than one near-identical action per queue.
 * Assignment isn't itself a moderation decision, so unlike the actions in
 * this namespace it writes no compliance log entry or statement of
 * reasons.
 */
class AssignQueueItem
{
    /**
     * @param  Review|Flag|ModerationIncident  $item
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, Model $item, ?User $assignee): Model
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can assign a queue item.');
        }

        if (! $item instanceof Review && ! $item instanceof Flag && ! $item instanceof ModerationIncident) {
            throw ValidationException::withMessages(['item' => 'This item type cannot be assigned.']);
        }

        if ($assignee !== null && $assignee->staffRole() === null) {
            throw ValidationException::withMessages(['assignee' => 'A queue item can only be assigned to a staff member.']);
        }

        $item->update(['assigned_to' => $assignee?->id]);

        return $item->fresh();
    }
}
