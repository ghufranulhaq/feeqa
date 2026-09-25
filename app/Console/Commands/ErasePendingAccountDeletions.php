<?php

namespace App\Console\Commands;

use App\Actions\Accounts\EraseUserAccount;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-001-20: "Personal data is erased or irreversibly pseudonymised within
 * 30 days." Scheduled daily — see routes/console.php.
 */
#[Signature('accounts:erase-pending-deletions')]
#[Description('Pseudonymise accounts whose 30-day deletion window has passed')]
class ErasePendingAccountDeletions extends Command
{
    public function handle(EraseUserAccount $action): int
    {
        $users = User::whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', now()->subDays(30))
            ->get();

        foreach ($users as $user) {
            $action->handle($user);
        }

        $this->info("Erased {$users->count()} account(s).");

        return self::SUCCESS;
    }
}
