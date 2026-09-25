<?php

namespace App\Actions\Accounts;

use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Edge cases table: reject > 5 MB, not an image, animated, or a failed
 * malware scan. Accepted files are always re-encoded through GD before
 * storage, which strips EXIF as a side effect of decode+re-encode.
 */
class UpdateAvatar
{
    public function __construct(private readonly MalwareScanner $scanner) {}

    public function handle(User $user, UploadedFile $file): void
    {
        if ($this->isAnimated($file)) {
            throw ValidationException::withMessages(['avatar' => 'Animated images are not allowed.']);
        }

        $scan = $this->scanner->scan($file->getRealPath());

        if (! $scan->clean) {
            throw ValidationException::withMessages(['avatar' => 'This file failed a malware scan.']);
        }

        $stripped = $this->stripExif($file);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $extension = $file->getClientOriginalExtension() ?: 'jpg';
        $path = 'avatars/'.$user->id.'-'.now()->timestamp.'-'.Str::random(8).'.'.$extension;

        Storage::disk('public')->put($path, $stripped);

        $user->forceFill(['avatar_path' => $path])->save();
    }

    public function remove(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
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

    /**
     * Decoding and re-encoding through GD drops EXIF metadata — GD never
     * writes it back out unless told to explicitly, which we don't.
     */
    private function stripExif(UploadedFile $file): string
    {
        $image = match ($file->getMimeType()) {
            'image/jpeg' => imagecreatefromjpeg($file->getRealPath()),
            'image/png' => imagecreatefrompng($file->getRealPath()),
            'image/webp' => imagecreatefromwebp($file->getRealPath()),
            default => throw ValidationException::withMessages(['avatar' => 'Unsupported image type.']),
        };

        if ($image === false) {
            throw ValidationException::withMessages(['avatar' => 'That file could not be read as an image.']);
        }

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
