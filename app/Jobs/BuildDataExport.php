<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Notifications\DataExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * FR-001-19: "delivered ... within 24 hours". Queued so a request never
 * blocks on it, though in practice (no huge media libraries yet) this
 * finishes in well under a second.
 */
class BuildDataExport implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $dataExportId) {}

    public function handle(): void
    {
        $export = DataExport::findOrFail($this->dataExportId);
        $user = $export->user;

        $tempDir = storage_path('app/tmp/export-'.$export->id);
        @mkdir($tempDir, recursive: true);

        $sections = [];

        foreach (config('platform.export.collectors') as $collectorClass) {
            $collector = app($collectorClass);
            $name = $collector->exportSectionName();

            $sections[$name] = $collector->collectExportData($user);

            foreach ($collector->collectExportMedia($user) as $exportPath => $sourcePath) {
                $mediaDir = "{$tempDir}/media/{$name}";
                @mkdir($mediaDir, recursive: true);
                @copy($sourcePath, "{$mediaDir}/{$exportPath}");
            }
        }

        file_put_contents(
            "{$tempDir}/data.json",
            json_encode($sections, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        $zipPath = "{$tempDir}.zip";
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $this->addDirectoryToZip($zip, $tempDir, '');
        $zip->close();

        $storedPath = 'exports/user-'.$user->id.'-'.$export->id.'.zip';
        Storage::disk('local')->put($storedPath, file_get_contents($zipPath));

        $this->cleanUp($tempDir, $zipPath);

        $export->forceFill([
            'status' => 'ready',
            'file_path' => $storedPath,
            'ready_at' => now(),
            'expires_at' => now()->addDays((int) config('platform.export.download_link_days')),
        ])->save();

        $user->notify(new DataExportReadyNotification($export));
    }

    private function addDirectoryToZip(ZipArchive $zip, string $dir, string $prefix): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = "{$dir}/{$entry}";

            if (is_dir($path)) {
                $this->addDirectoryToZip($zip, $path, "{$prefix}{$entry}/");
            } else {
                $zip->addFile($path, "{$prefix}{$entry}");
            }
        }
    }

    private function cleanUp(string $tempDir, string $zipPath): void
    {
        @unlink($zipPath);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($tempDir);
    }
}
