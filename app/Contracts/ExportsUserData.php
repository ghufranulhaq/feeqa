<?php

namespace App\Contracts;

use App\Models\User;

/**
 * FR-001-19: "Data export contains every personal-data entity defined
 * across all shipped specs" (spec 001 §6 acceptance criteria). Each spec
 * that adds personal data implements this and registers itself in
 * config('platform.export.collectors') so BuildDataExport picks it up
 * without spec 001 needing to know what those specs are.
 */
interface ExportsUserData
{
    /**
     * A short name for this section of the export (used as both the JSON
     * key and, if $media is non-empty, the media subfolder name).
     */
    public function exportSectionName(): string;

    /**
     * @return array<string, mixed> JSON-serializable data for this user.
     */
    public function collectExportData(User $user): array;

    /**
     * Absolute source paths of any files to include (e.g. an avatar, a
     * proof upload) — exported path relative to the section's media
     * folder => absolute path on disk.
     *
     * @return array<string, string>
     */
    public function collectExportMedia(User $user): array;
}
