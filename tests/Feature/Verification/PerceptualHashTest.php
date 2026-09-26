<?php

use App\Domain\Verification\PerceptualHash;

/**
 * 8 blocks x 8 blocks of solid black/white so each block maps to exactly
 * one of the 8x8 average-hash pixels after scaling — makes the resulting
 * hash predictable enough to assert against.
 */
function makeCheckerboardPng(bool $invert = false, bool $withNoise = false): string
{
    $size = 64;
    $blockSize = 8;

    $image = imagecreatetruecolor($size, $size);
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);

    for ($by = 0; $by < 8; $by++) {
        for ($bx = 0; $bx < 8; $bx++) {
            $isWhite = ($bx + $by) % 2 === 0;

            if ($invert) {
                $isWhite = ! $isWhite;
            }

            imagefilledrectangle(
                $image,
                $bx * $blockSize,
                $by * $blockSize,
                $bx * $blockSize + $blockSize - 1,
                $by * $blockSize + $blockSize - 1,
                $isWhite ? $white : $black,
            );
        }
    }

    if ($withNoise) {
        $gray = imagecolorallocate($image, 128, 128, 128);
        imagesetpixel($image, 1, 1, $gray);
        imagesetpixel($image, 2, 1, $gray);
        imagesetpixel($image, 1, 2, $gray);
    }

    $path = tempnam(sys_get_temp_dir(), 'phash').'.png';
    imagepng($image, $path);
    imagedestroy($image);

    return $path;
}

it('produces the same hash for the same image (FR-004-10)', function () {
    $path = makeCheckerboardPng();

    expect(PerceptualHash::compute($path))->toBe(PerceptualHash::compute($path));
});

it('produces a hash of 16 hex characters (64 bits)', function () {
    $hash = PerceptualHash::compute(makeCheckerboardPng());

    expect($hash)->toMatch('/^[0-9a-f]{16}$/');
});

it('gives near-duplicate images a small Hamming distance (FR-004-07)', function () {
    $original = PerceptualHash::compute(makeCheckerboardPng());
    $withNoise = PerceptualHash::compute(makeCheckerboardPng(withNoise: true));

    expect(PerceptualHash::hammingDistance($original, $withNoise))->toBeLessThanOrEqual(2);
});

it('gives a wildly different image a large Hamming distance', function () {
    $original = PerceptualHash::compute(makeCheckerboardPng());
    $inverted = PerceptualHash::compute(makeCheckerboardPng(invert: true));

    expect(PerceptualHash::hammingDistance($original, $inverted))->toBeGreaterThan(32);
});

it('reports zero distance between a hash and itself', function () {
    $hash = PerceptualHash::compute(makeCheckerboardPng());

    expect(PerceptualHash::hammingDistance($hash, $hash))->toBe(0);
});

it('throws on an unsupported image type', function () {
    $path = tempnam(sys_get_temp_dir(), 'notimage').'.txt';
    file_put_contents($path, 'not an image');

    PerceptualHash::compute($path);
})->throws(RuntimeException::class);
