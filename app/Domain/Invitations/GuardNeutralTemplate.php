<?php

namespace App\Domain\Invitations;

use App\Domain\Businesses\DomainNormalizer;

/**
 * FR-005-13, FR-005-14: rejects a template that mentions an incentive,
 * asks only for positive reviews or suggests a rating, pre-selects a
 * rating/sentiment before the review link ("gating"), or links anywhere
 * other than the review link, the business's own domain, or unsubscribe.
 * Lexicon-based, same "rules only, no external provider to swap" shape as
 * `App\Domain\Reviews` screening (constitution §5.1 doesn't apply here —
 * there's nothing to fake). A locale outside `KNOWN_LOCALES` skips the
 * incentive lexicon (there's no translation for it) — the caller is
 * responsible for holding that template for staff review before use
 * (edge case table) rather than treating a lexicon miss as a pass.
 */
final class GuardNeutralTemplate
{
    public const KNOWN_LOCALES = ['en-GB', 'fr', 'de', 'es'];

    /**
     * @var array<string, list<string>>
     */
    private const INCENTIVE_LEXICON = [
        'en-GB' => [
            'discount', 'coupon', 'prize', 'gift card', 'gift', 'voucher', 'refund',
            'cashback', 'cash back', 'points', 'reward', 'in return', 'free gift',
            'win a', 'giveaway', 'sweepstake', 'enter our draw', 'entry into',
        ],
        'fr' => [
            'réduction', 'remise', 'coupon', 'cadeau', 'remboursement', 'points fidélité',
            'récompense', 'en échange', 'gratuit', 'gagner', 'tirage au sort',
        ],
        'de' => [
            'rabatt', 'gutschein', 'geschenk', 'erstattung', 'punkte', 'belohnung',
            'im gegenzug', 'kostenlos', 'gewinnen', 'verlosung',
        ],
        'es' => [
            'descuento', 'cupón', 'regalo', 'reembolso', 'puntos', 'recompensa',
            'a cambio', 'gratis', 'ganar', 'sorteo',
        ],
    ];

    /**
     * @var list<string>
     */
    private const RATING_SUGGESTION_PATTERNS = [
        '/\b(five|5)[\s-]?stars?\b/i',
        '/\bleave (us )?(a )?(great|5|five|positive|glowing)[\s-]?(star )?review\b/i',
        '/\bgive us (a )?(great|5|five|top)[\s-]?(star )?rating\b/i',
        '/\brate us (highly|5 ?stars?|five ?stars?)\b/i',
        '/\bonly (leave|write) a review if\b/i',
        '/\bif you (were|are) (happy|satisfied|pleased)\b.*\breview\b/is',
    ];

    /**
     * @var list<string>
     */
    private const GATING_PATTERNS = [
        '/\bif you (had|have) a great experience\b/i',
        '/\bif you were (happy|satisfied) with\b/i',
        '/\bclick (below|here) based on your experience\b/i',
        '/\bwere you satisfied\?.*\b(yes|no)\b/is',
        '/☆|★/u',
    ];

    /**
     * @param  list<string>  $allowedDomains  the business's own registered domains (normalised)
     * @return list<string> validation errors; empty means the template passes
     */
    public static function check(string $subject, string $body, string $locale, array $allowedDomains): array
    {
        $errors = [];
        $haystack = $subject.' '.$body;

        if (! str_contains($body, '{review_link}')) {
            $errors[] = 'The template must include the {review_link} placeholder.';
        }

        if (! str_contains($body, '{unsubscribe_link}')) {
            $errors[] = 'The template must include the {unsubscribe_link} placeholder.';
        }

        if (in_array($locale, self::KNOWN_LOCALES, true) && self::mentionsIncentive($haystack, $locale)) {
            $errors[] = 'The template must not mention any incentive for leaving a review.';
        }

        if (self::suggestsOnlyPositiveReviews($haystack)) {
            $errors[] = 'The template must not ask only for positive reviews or suggest a rating.';
        }

        if (self::gatesOnSentiment($body)) {
            $errors[] = 'The template must not pre-select a rating or sentiment before the review link ("review gating").';
        }

        foreach (self::disallowedLinks($body, $allowedDomains) as $link) {
            $errors[] = "The template links to \"{$link}\", which isn't the review link, the business's own website, or unsubscribe.";
        }

        return $errors;
    }

    private static function mentionsIncentive(string $haystack, string $locale): bool
    {
        $lower = mb_strtolower($haystack);

        foreach (self::INCENTIVE_LEXICON[$locale] as $term) {
            if (str_contains($lower, $term)) {
                return true;
            }
        }

        return false;
    }

    private static function suggestsOnlyPositiveReviews(string $haystack): bool
    {
        foreach (self::RATING_SUGGESTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function gatesOnSentiment(string $body): bool
    {
        $linkPosition = mb_strpos($body, '{review_link}');
        $beforeLink = $linkPosition === false ? $body : mb_substr($body, 0, $linkPosition);

        foreach (self::GATING_PATTERNS as $pattern) {
            if (preg_match($pattern, $beforeLink) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $allowedDomains
     * @return list<string> the disallowed URLs found, verbatim
     */
    private static function disallowedLinks(string $body, array $allowedDomains): array
    {
        preg_match_all('#https?://[^\s)\]}"\']+#i', $body, $matches);

        $disallowed = [];

        foreach ($matches[0] as $url) {
            $host = DomainNormalizer::normalize($url);

            if ($host === null || in_array($host, $allowedDomains, true)) {
                continue;
            }

            $disallowed[] = $url;
        }

        return $disallowed;
    }
}
