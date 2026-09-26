<?php

namespace App\Actions\Staff;

use App\Models\AuditSample;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-20: a staff member's correct/incorrect verdict on one sampled
 * automated decision — quality control feeding `ComputeRulePrecision`,
 * not a moderation or enforcement decision against a user, so (like
 * `AssignQueueItem`) this doesn't write a compliance log entry.
 */
class DecideAuditSample
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, AuditSample $sample, bool $correct): AuditSample
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can decide an audit sample.');
        }

        if (! $sample->isPending()) {
            throw ValidationException::withMessages(['sample' => 'This audit sample has already been decided.']);
        }

        $sample->update([
            'correct' => $correct,
            'staff_id' => $staff->id,
            'decided_at' => now(),
        ]);

        return $sample->fresh();
    }
}
