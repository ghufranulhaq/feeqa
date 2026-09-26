<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Models\VerificationAttestation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * FR-001-20: "Personal data is erased or irreversibly pseudonymised within
 * 30 days." Pseudonymises rather than hard-deletes the row: other specs
 * will have foreign keys onto users.id (reviews, cases…), and a hard
 * delete would either cascade-erase content the platform needs to keep
 * (constitution §5.1: a review outliving its author isn't the same
 * question as the author's own personal data) or fail on a restrictive
 * FK — pseudonymising sidesteps both. Compliance log entries that target
 * this user keep only this now-meaningless numeric ID (FR-001-20).
 */
class EraseUserAccount
{
    public function handle(User $user): void
    {
        $this->revokeAttestations($user);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill([
            'name' => 'Deleted user',
            'email' => "deleted-{$user->id}@erased.invalid",
            'password' => null,
            'remember_token' => null,
            'country' => null,
            'locale' => 'en-GB',
            'avatar_path' => null,
        ])->save();

        foreach ($user->dataExports as $export) {
            if ($export->file_path) {
                Storage::disk('local')->delete($export->file_path);
            }
        }

        $user->providers()->delete();
        $user->consents()->delete();
        $user->dataExports()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }

    /**
     * Spec 004 edge case: "Consumer deletes their account: attestations
     * are revoked with the reason `review_deleted`, and fingerprints are
     * kept (without personal data) to prevent reuse." Not a staff
     * decision, so it skips RevokeAttestation's staff-role guard and
     * compliance log entry — this is automatic pseudonymisation, not
     * moderation (constitution §5.1's compliance log is staff decisions
     * only). The review itself, and its verification's proof fingerprint,
     * are left untouched.
     */
    private function revokeAttestations(User $user): void
    {
        VerificationAttestation::query()
            ->whereHas('review', fn (Builder $query) => $query->where('reviewer_id', $user->id))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoked_reason_code' => 'review_deleted']);
    }
}
