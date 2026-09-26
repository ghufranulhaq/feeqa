<?php

namespace App\Domain\Verification;

use GdImage;
use RuntimeException;

/**
 * FR-004-07, FR-004-10: an 8x8 average hash (aHash), the same GD-based
 * approach as App\Actions\Accounts\UpdateAvatar's re-encode step, reused
 * here to fingerprint image content rather than strip EXIF. Returned as
 * 16 hex characters (64 bits); near-duplicates have a small Hamming
 * distance between their hashes.
 */
final class PerceptualHash
{
    public static function compute(string $filePath): string
    {
        $image = self::load($filePath);
        $small = imagescale($image, 8, 8);
        imagedestroy($image);

        if ($small === false) {
            throw new RuntimeException('Could not scale image for perceptual hash.');
        }

        $values = [];

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorsforindex($small, imagecolorat($small, $x, $y));
                $values[] = (int) round(($rgb['red'] + $rgb['green'] + $rgb['blue']) / 3);
            }
        }

        imagedestroy($small);

        $average = array_sum($values) / count($values);

        $bits = '';

        foreach ($values as $value) {
            $bits .= $value >= $average ? '1' : '0';
        }

        $hex = '';

        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex(bindec($nibble));
        }

        return $hex;
    }

    public static function hammingDistance(string $a, string $b): int
    {
        $bytesA = hex2bin(str_pad($a, 16, '0'));
        $bytesB = hex2bin(str_pad($b, 16, '0'));

        $distance = 0;

        for ($i = 0; $i < 8; $i++) {
            $distance += substr_count(decbin(ord($bytesA[$i]) ^ ord($bytesB[$i])), '1');
        }

        return $distance;
    }

    private static function load(string $filePath): GdImage
    {
        $info = getimagesize($filePath);

        if ($info === false) {
            throw new RuntimeException('Could not read image dimensions.');
        }

        $image = match ($info['mime']) {
            'image/jpeg' => imagecreatefromjpeg($filePath),
            'image/png' => imagecreatefrompng($filePath),
            'image/webp' => imagecreatefromwebp($filePath),
            default => throw new RuntimeException("Unsupported image type \"{$info['mime']}\" for perceptual hash."),
        };

        if ($image === false) {
            throw new RuntimeException('Could not decode image.');
        }

        return $image;
    }
}
