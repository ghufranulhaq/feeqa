<?php

namespace App\Actions\Moderation;

use App\Domain\Moderation\GuidelineAudience;
use App\Models\GuidelineVersion;
use Illuminate\Support\Collection;

/**
 * FR-006-01, FR-006-21(a): the first piece of the Transparency Center —
 * fully public, no authentication. Returns both audiences' current
 * version plus their full, never-deleted history, newest first.
 */
class ListGuidelineVersions
{
    /**
     * @return array<string, array{current: ?GuidelineVersion, history: Collection<int, GuidelineVersion>}>
     */
    public function handle(): array
    {
        $result = [];

        foreach (GuidelineAudience::cases() as $audience) {
            $versions = GuidelineVersion::query()
                ->where('audience', $audience)
                ->orderByDesc('version')
                ->get();

            $result[$audience->value] = [
                'current' => $versions->firstWhere('is_current', true),
                'history' => $versions,
            ];
        }

        return $result;
    }
}
