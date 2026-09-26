<?php

namespace App\Domain\Verification;

/**
 * FR-004-04: "content-type sniffing (not extension)." Reads magic bytes
 * directly rather than trusting the client's extension or MIME type
 * header — the same reasoning as UpdateAvatar's re-encode step, just
 * applied to a wider set of proof file types.
 */
final class SniffProofFileType
{
    private const HEIC_BRANDS = ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1', 'heim', 'heis'];

    public static function detect(string $filePath): ?ProofFileType
    {
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 32) ?: '';
        fclose($handle);

        return match (true) {
            str_starts_with($header, '%PDF-') => ProofFileType::Pdf,
            str_starts_with($header, "\xFF\xD8\xFF") => ProofFileType::Jpeg,
            str_starts_with($header, "\x89PNG\r\n\x1a\n") => ProofFileType::Png,
            self::isHeic($header) => ProofFileType::Heic,
            self::isEml($filePath) => ProofFileType::Eml,
            default => null,
        };
    }

    private static function isHeic(string $header): bool
    {
        if (strlen($header) < 12 || substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        return in_array(substr($header, 8, 4), self::HEIC_BRANDS, true);
    }

    /**
     * An `.eml` file is a plain-text RFC 822 message — there's no magic
     * byte signature, only the shape of the headers at the top of the file.
     */
    private static function isEml(string $filePath): bool
    {
        $sample = file_get_contents($filePath, false, null, 0, 4096) ?: '';

        if ($sample === '' || str_contains($sample, "\x00")) {
            return false;
        }

        return (bool) preg_match('/^(From|To|Subject|Return-Path|Received|Message-ID|Date):/mi', $sample);
    }
}
