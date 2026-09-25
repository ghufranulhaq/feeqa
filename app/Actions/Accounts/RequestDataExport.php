<?php

namespace App\Actions\Accounts;

use App\Jobs\BuildDataExport;
use App\Models\DataExport;
use App\Models\User;

/**
 * FR-001-19, edge case: "Export requested twice within 24 hours → return
 * the pending export. Do not start a second one."
 */
class RequestDataExport
{
    public function handle(User $user): DataExport
    {
        $existing = $user->dataExports()->requestedRecently()->latest('requested_at')->first();

        if ($existing) {
            return $existing;
        }

        $export = $user->dataExports()->create([
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        BuildDataExport::dispatch($export->id);

        return $export;
    }
}
