<?php

namespace App\Actions\Staff;

use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * FR-001-14: "Staff accounts may be created only by a staff Admin."
 * FR-001-15: creating a staff account is logged — it's a significant
 * account-level action even though it isn't moderation of a *consumer's*
 * content.
 */
class CreateStaffAccount
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, string $name, string $email, StaffRole $role): User
    {
        if ($actor->staffRole() !== StaffRole::Admin) {
            throw new AuthorizationException('Only a staff Admin can create staff accounts.');
        }

        $user = User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make(Str::random(40)),
            'date_of_birth_confirmed_at' => now(),
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'staff_role' => $role->value,
        ])->save();

        ComplianceLogEntry::record(
            staff: $actor,
            action: 'staff_account_created',
            reasonCode: 'staff_provisioning',
            target: $user,
        );

        // Created with an unusable random password — send them the normal
        // "set your password" flow rather than emailing one.
        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }
}
