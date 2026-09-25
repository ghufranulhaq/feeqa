<?php

namespace App\Actions\Accounts;

use App\Models\User;
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
}
