<?php

namespace App\Domain\Verification;

/**
 * Edge cases table: "corrupt, password-protected PDF > reject with a
 * specific message." No PDF-parsing dependency has been approved for this
 * project, so this is a heuristic, structural read rather than a full
 * parse — every real PDF has a `startxref`/`%%EOF` trailer, and an
 * encrypted one declares an `/Encrypt` dictionary in its trailer/object
 * table, both plain ASCII markers findable without decoding the file.
 */
final class InspectPdf
{
    public static function isPasswordProtected(string $filePath): bool
    {
        return str_contains(file_get_contents($filePath) ?: '', '/Encrypt');
    }

    public static function isReadable(string $filePath): bool
    {
        $contents = file_get_contents($filePath) ?: '';

        if ($contents === '' || ! str_starts_with($contents, '%PDF-')) {
            return false;
        }

        $tail = substr($contents, -2048);

        return str_contains($tail, 'startxref') && str_contains($tail, '%%EOF');
    }
}
