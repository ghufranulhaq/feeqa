<?php

namespace App\Support\Reviews;

/**
 * Edge case table: "Strip all markup and store plain text only. Render
 * links as non-clickable text unless they are to the reviewed business's
 * own domain." `sanitize()` runs at input (T3, T10) so nothing but plain
 * text is ever stored; `linkifyOwnDomain()` runs at read time.
 */
class ReviewText
{
    public static function sanitize(string $raw): string
    {
        $withoutScripts = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $raw) ?? $raw;
        $withoutTags = strip_tags($withoutScripts);
        $decoded = html_entity_decode($withoutTags, ENT_QUOTES | ENT_HTML5);

        return trim($decoded);
    }

    /**
     * Escapes the whole text first, so nothing but the reviewed business's
     * own domain can ever become a link — every other URL, tag, or entity
     * in the text renders as inert text.
     */
    public static function linkifyOwnDomain(string $text, string $domain): string
    {
        $escaped = e($text);
        $domain = trim($domain);

        if ($domain === '') {
            return $escaped;
        }

        $escapedDomain = e($domain);
        $pattern = '/(?<![\w.-])'.preg_quote($escapedDomain, '/').'(?![\w.-])/i';

        $linked = preg_replace_callback(
            $pattern,
            fn (array $match): string => '<a href="https://'.$escapedDomain.'" rel="nofollow noopener" target="_blank">'.$match[0].'</a>',
            $escaped,
        );

        return $linked ?? $escaped;
    }
}
