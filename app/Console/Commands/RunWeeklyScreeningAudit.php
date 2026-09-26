<?php

namespace App\Console\Commands;

use App\Actions\Staff\AuditScreeningSample;
use App\Actions\Staff\ComputeRulePrecision;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-006-20: "at least 2% ... sampled every week" and "any auto-reject
 * rule that falls below 99% precision is disabled automatically" — one
 * weekly job covering both halves of the same requirement. Scheduled
 * weekly — see routes/console.php.
 */
#[Signature('moderation:weekly-audit')]
#[Description('Sample the week\'s automated screening decisions and disable any auto-reject rule under 99% precision')]
class RunWeeklyScreeningAudit extends Command
{
    public function handle(AuditScreeningSample $sample, ComputeRulePrecision $precision): int
    {
        $sampled = $sample->handle();
        $this->info("Sampled {$sampled->count()} screening(s) for audit.");

        foreach ($precision->handle() as $ruleId => $value) {
            $this->info(sprintf('%s precision: %.1f%%', $ruleId, $value * 100));
        }

        return self::SUCCESS;
    }
}
