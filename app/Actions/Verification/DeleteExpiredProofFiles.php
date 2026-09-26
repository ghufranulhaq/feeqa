<?php

namespace App\Actions\Verification;

use App\Models\ReviewVerification;
use App\Support\Environment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * FR-004-23, constitution §5.3: 30 days after a verification decision
 * (approved or rejected), raw proof files are deleted and
 * `extracted_fields` is cleared, since none of it is kept in the signed
 * attestation (T4). A no-op wherever
 * {@see Environment::rawProofFilesDeletedOnSchedule()} says files should
 * stay — the demo server, per constitution §5.6.
 */
class DeleteExpiredProofFiles
{
    public function handle(): int
    {
        if (! Environment::rawProofFilesDeletedOnSchedule()) {
            return 0;
        }

        $verifications = ReviewVerification::query()
            ->whereNull('proof_deleted_at')
            ->whereNotNull('decided_at')
            ->where('decided_at', '<=', now()->subDays(30))
            ->where(fn (Builder $query) => $query->whereNotNull('proof_paths')->orWhereNotNull('extracted_fields'))
            ->get();

        foreach ($verifications as $verification) {
            if ($verification->proof_paths !== null) {
                Storage::disk('local')->delete($verification->proof_paths);
            }

            $verification->forceFill([
                'proof_paths' => null,
                'extracted_fields' => null,
                'proof_deleted_at' => now(),
            ])->save();
        }

        return $verifications->count();
    }
}
