<?php

namespace App\Domain\Invitations;

/**
 * FR-005-06, FR-005-07: a plain header/body reader for the demo `.eml`
 * stand-in (plan D14 — no real inbound-mail infrastructure exists). It
 * trusts the `Authentication-Results` header a real receiving mail server
 * would already have written after doing the actual SPF/DKIM cryptography
 * — this class never re-verifies a signature itself, the same "rules
 * only, nothing to swap" shape as review screening.
 */
final class RawEmailParser
{
    public static function parse(string $raw): ParsedEmail
    {
        [$headers, $body] = self::splitHeadersAndBody($raw);

        $subject = self::header($headers, 'subject');
        [$recipientEmail, $recipientName] = self::firstToRecipient($headers);
        [$spfPass, $dkimPass, $dkimDomain, $spfMailFromDomain] = self::authenticationResults($headers);

        return new ParsedEmail(
            recipientEmail: $recipientEmail,
            recipientName: $recipientName,
            subject: $subject,
            fromAddress: self::extractAddress(self::header($headers, 'from') ?? ''),
            spfPass: $spfPass,
            dkimPass: $dkimPass,
            dkimDomain: $dkimDomain,
            spfMailFromDomain: $spfMailFromDomain,
            searchableText: trim(($subject ?? '')."\n".$body),
        );
    }

    /**
     * @return array{0: array<string, list<string>>, 1: string}
     */
    private static function splitHeadersAndBody(string $raw): array
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = array_pad(preg_split('/\n\n/', $normalized, 2) ?: [], 2, '');

        // RFC 2822 header folding: a continuation line starts with whitespace.
        $unfolded = preg_replace('/\n[ \t]+/', ' ', (string) $headerBlock) ?? '';

        $headers = [];
        foreach (explode("\n", $unfolded) as $line) {
            if (trim($line) === '') {
                continue;
            }

            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))][] = trim($value);
        }

        return [$headers, trim((string) $body)];
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        return $headers[$name][0] ?? null;
    }

    /**
     * FR-005-07 edge case: "BCC email with multiple recipients — invite
     * only the first To: recipient. Ignore CC." Cc is never even read.
     *
     * @param  array<string, list<string>>  $headers
     * @return array{0: ?string, 1: ?string}
     */
    private static function firstToRecipient(array $headers): array
    {
        $to = self::header($headers, 'to');

        if ($to === null || trim($to) === '') {
            return [null, null];
        }

        $entries = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $to) ?: [];
        $first = trim($entries[0] ?? '');

        return [self::extractAddress($first), self::extractDisplayName($first)];
    }

    private static function extractAddress(string $entry): ?string
    {
        if (preg_match('/<([^>]+)>/', $entry, $matches) === 1) {
            return trim($matches[1]) ?: null;
        }

        $trimmed = trim($entry);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function extractDisplayName(string $entry): ?string
    {
        $withoutAddress = trim((string) preg_replace('/<[^>]+>/', '', $entry));
        $name = trim($withoutAddress, " \t\"");

        return $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, list<string>>  $headers
     * @return array{0: bool, 1: bool, 2: ?string, 3: ?string}
     */
    private static function authenticationResults(array $headers): array
    {
        $line = self::header($headers, 'authentication-results');

        if ($line === null) {
            return [false, false, null, null];
        }

        $spfPass = (bool) preg_match('/\bspf=pass\b/i', $line);
        $dkimPass = (bool) preg_match('/\bdkim=pass\b/i', $line);
        $dkimDomain = preg_match('/header\.d=([\w.-]+)/i', $line, $m) === 1 ? strtolower($m[1]) : null;
        $spfMailFromDomain = preg_match('/smtp\.mailfrom=(?:[^@;]*@)?([\w.-]+)/i', $line, $m) === 1 ? strtolower($m[1]) : null;

        return [$spfPass, $dkimPass, $dkimDomain, $spfMailFromDomain];
    }
}
