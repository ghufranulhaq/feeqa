<?php

namespace App\Actions\Businesses;

use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-03 (JPEG/PNG/WebP, ≤ 2 MB, ≥ 200×200) and the edge cases table:
 * reject animated, keep transparency. Mirrors App\Actions\Accounts\
 * UpdateAvatar's image pipeline (own copy, not shared: different size/
 * dimension limits, and a logo keeps its alpha channel instead of it
 * being incidental).
 */
class UpdateBusinessLogo
{
    public function __construct(private readonly MalwareScanner $scanner) {}

    public function handle(Business $business, UploadedFile $file): void
    {
        if ($this->isAnimated($file)) {
            throw ValidationException::withMessages(['logo' => 'Animated images are not allowed.']);
        }

        [$width, $height] = getimagesize($file->getRealPath()) ?: [0, 0];

        if ($width < 200 || $height < 200) {
            throw ValidationException::withMessages(['logo' => 'The logo must be at least 200×200 pixels.']);
        }

        $scan = $this->scanner->scan($file->getRealPath());

        if (! $scan->clean) {
            throw ValidationException::withMessages(['logo' => 'This file failed a malware scan.']);
        }

        $encoded = $this->reencode($file);

        if ($business->logo_path) {
            Storage::disk('public')->delete($business->logo_path);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $path = 'business-logos/'.$business->id.'-'.now()->timestamp.'-'.Str::random(8).'.'.$extension;

        Storage::disk('public')->put($path, $encoded);

        $business->forceFill(['logo_path' => $path])->save();
    }

    /**
     * Heuristic: an animated WebP's RIFF container has an "ANIM" chunk.
     * JPEG/PNG (the only other allowed types) can never be animated.
     */
    private function isAnimated(UploadedFile $file): bool
    {
        if ($file->getMimeType() !== 'image/webp') {
            return false;
        }

        return str_contains((string) file_get_contents($file->getRealPath()), 'ANIM');
    }

    private function reencode(UploadedFile $file): string
    {
        $image = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => throw ValidationException::withMessages(['logo' => 'Unsupported image type.']),
        };

        if ($image === false) {
            throw ValidationException::withMessages(['logo' => 'That file could not be read as an image.']);
        }

        // Edge cases table: a transparent logo is accepted, not flattened.
        imagesavealpha($image, true);

        ob_start();

        match ($file->getMimeType()) {
            'image/jpeg' => imagejpeg($image, quality: 90),
            'image/png' => imagepng($image),
            'image/webp' => imagewebp($image, quality: 90),
        };

        $contents = ob_get_clean();

        imagedestroy($image);

        return $contents;
    }
}
