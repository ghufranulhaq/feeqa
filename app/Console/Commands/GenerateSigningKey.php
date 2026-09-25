<?php

namespace App\Console\Commands;

use App\Drivers\Signing\SigningService;
use Illuminate\Console\Command;

class GenerateSigningKey extends Command
{
    protected $signature = 'signing:generate-key';

    protected $description = 'Generate a new Ed25519 signing keypair (plan D16) and add it to the keys file';

    public function handle(SigningService $signing): int
    {
        $kid = $signing->generateKey();

        $this->info("Generated signing key: {$kid}");
        $this->line('Set SIGNING_ACTIVE_KID='.$kid.' in .env to pin it, or leave blank to use it automatically (first key wins).');

        return self::SUCCESS;
    }
}
