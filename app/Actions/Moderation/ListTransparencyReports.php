<?php

namespace App\Actions\Moderation;

use App\Models\TransparencyReport;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-006-21(e): every generated quarterly report, newest first — fully
 * public, no authentication, same shape as `ListGuidelineVersions`.
 */
class ListTransparencyReports
{
    /**
     * @return Collection<int, TransparencyReport>
     */
    public function handle(): Collection
    {
        return TransparencyReport::query()->orderByDesc('period_start')->get();
    }
}
