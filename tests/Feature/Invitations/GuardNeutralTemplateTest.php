<?php

use App\Domain\Invitations\GuardNeutralTemplate;

const NEUTRAL_SUBJECT = 'How was your experience with {business_name}?';
const NEUTRAL_BODY = "Hi {recipient_name},\n\nWe'd value your honest feedback about your recent booking.\n\n{review_link}\n\nDon't want these emails? {unsubscribe_link}";

it('passes a neutral template with both required placeholders (FR-005-13)', function () {
    $errors = GuardNeutralTemplate::check(NEUTRAL_SUBJECT, NEUTRAL_BODY, 'en-GB', []);

    expect($errors)->toBe([]);
});

it('rejects a template missing {review_link}', function () {
    $body = str_replace('{review_link}', '', NEUTRAL_BODY);

    expect(GuardNeutralTemplate::check(NEUTRAL_SUBJECT, $body, 'en-GB', []))->not->toBe([]);
});

it('rejects a template missing {unsubscribe_link}', function () {
    $body = str_replace('{unsubscribe_link}', '', NEUTRAL_BODY);

    expect(GuardNeutralTemplate::check(NEUTRAL_SUBJECT, $body, 'en-GB', []))->not->toBe([]);
});

it('allows a link to the business\'s own registered domain', function () {
    $body = NEUTRAL_BODY."\n\nSee our other services at https://www.acme-travel.com/services";

    expect(GuardNeutralTemplate::check(NEUTRAL_SUBJECT, $body, 'en-GB', ['acme-travel.com']))->toBe([]);
});

it('rejects a link to a domain other than the review link, the business\'s own domain, or unsubscribe', function () {
    $body = NEUTRAL_BODY."\n\nAlso check out https://not-the-business.example/promo";

    expect(GuardNeutralTemplate::check(NEUTRAL_SUBJECT, $body, 'en-GB', ['acme-travel.com']))->not->toBe([]);
});

it('holds an unrecognised locale out of the incentive lexicon so the caller can flag it for staff review', function () {
    // Portuguese isn't in KNOWN_LOCALES: a lexicon-specific incentive word
    // in it can't be caught here, but the placeholder/rating/gating/link
    // checks are language-agnostic and still run.
    $errors = GuardNeutralTemplate::check(NEUTRAL_SUBJECT, NEUTRAL_BODY, 'pt', []);

    expect($errors)->toBe([])
        ->and(in_array('pt', GuardNeutralTemplate::KNOWN_LOCALES, true))->toBeFalse();
});

/**
 * FR-005-13 acceptance criterion: "test corpus of ≥ 50 negative examples
 * in launch locales". One dataset entry per row below; Pest expands each
 * into its own test case.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function neutralityViolationCorpus(): array
{
    $incentiveEnGB = [
        'Get 10% discount on your next order for leaving a review',
        'Use this coupon code after you review us',
        'Win a prize just for sharing your experience',
        'Here is a small gift for your feedback',
        'Enjoy a voucher worth £10 after reviewing',
        'Claim your refund faster by leaving a review first',
        'Get cashback when you review your recent stay',
        'Earn points every time you leave a review',
        'Leave a review in return for a free upgrade',
        'You get a free gift when you review us',
        'Win a getaway — just leave your review to enter',
        'Enter our giveaway by writing a review',
        'Join our sweepstake after reviewing',
        'Your entry into the draw needs a review first',
    ];

    $incentiveFr = [
        'Obtenez une réduction sur votre prochaine réservation en laissant un avis',
        'Utilisez ce coupon après avoir laissé un avis',
        'Un cadeau vous attend si vous partagez votre expérience',
        'Remboursement rapide si vous laissez un avis',
        'Gagnez des points fidélité en donnant votre avis',
        'Un avis en échange d’un cadeau surprise',
        'Ce service est gratuit si vous laissez un avis',
        'Participez à notre tirage au sort après votre avis',
    ];

    $incentiveDe = [
        'Erhalten Sie einen Rabatt für Ihre Bewertung',
        'Nutzen Sie diesen Gutschein nach Ihrer Bewertung',
        'Ein Geschenk wartet auf Sie nach Ihrer Bewertung',
        'Schnellere Erstattung, wenn Sie zuerst bewerten',
        'Sammeln Sie Punkte für jede Bewertung',
        'Eine Bewertung im Gegenzug für einen Rabatt',
        'Dieser Service ist kostenlos, wenn Sie bewerten',
        'Nehmen Sie an unserer Verlosung teil, nachdem Sie bewertet haben',
    ];

    $incentiveEs = [
        'Obtén un descuento en tu próxima reserva al dejar una reseña',
        'Usa este cupón después de tu reseña',
        'Un regalo te espera por compartir tu experiencia',
        'Reembolso más rápido si dejas una reseña primero',
        'Gana puntos por cada reseña',
        'Una reseña a cambio de un regalo sorpresa',
        'Este servicio es gratis si dejas una reseña',
        'Participa en nuestro sorteo después de tu reseña',
    ];

    $ratingSuggestion = [
        'Please leave us a five star review',
        'We would love a 5 star review from you',
        'Give us a great star rating today',
        'Rate us highly if you can',
        'Only leave a review if you loved your trip',
        'If you were happy with your trip, please review us',
        'Please leave us a positive review about your stay',
        'Give us a top star rating please',
    ];

    $gating = [
        'If you had a great experience, click below to review us',
        'If you were happy with your booking, click here to continue',
        'Click below based on your experience with us',
        'Were you satisfied? Yes or no, let us know first',
        '★★★★★ Tap the stars that match your stay',
    ];

    $foreignLinks = [
        "Also check our partner offers at https://partner-deals.example/win\n\n{review_link}\n\n{unsubscribe_link}",
        "Follow us on https://social-network.example/acme\n\n{review_link}\n\n{unsubscribe_link}",
        "Read more news at https://news.example/travel\n\n{review_link}\n\n{unsubscribe_link}",
    ];

    $cases = [];

    foreach ([
        'incentive-en-GB' => [$incentiveEnGB, 'en-GB'],
        'incentive-fr' => [$incentiveFr, 'fr'],
        'incentive-de' => [$incentiveDe, 'de'],
        'incentive-es' => [$incentiveEs, 'es'],
        'rating-suggestion' => [$ratingSuggestion, 'en-GB'],
        'gating' => [$gating, 'en-GB'],
    ] as $label => [$phrases, $locale]) {
        foreach ($phrases as $i => $phrase) {
            $body = $locale === 'en-GB' && str_contains($label, 'incentive')
                ? "{$phrase}\n\n{review_link}\n\n{unsubscribe_link}"
                : ($label === 'gating' ? "{$phrase} {review_link}\n\n{unsubscribe_link}" : "{$phrase}\n\n{review_link}\n\n{unsubscribe_link}");

            $cases["{$label} #{$i}: {$phrase}"] = [$body, $locale];
        }
    }

    foreach ($foreignLinks as $i => $body) {
        $cases["foreign-link #{$i}"] = [$body, 'en-GB'];
    }

    return $cases;
}

it('rejects every example in the ≥50-entry negative corpus (FR-005-13)', function (string $body, string $locale) {
    expect(GuardNeutralTemplate::check(NEUTRAL_SUBJECT, $body, $locale, []))->not->toBe([]);
})->with('neutralityViolationCorpus');

dataset('neutralityViolationCorpus', fn () => neutralityViolationCorpus());

it('has at least 50 negative examples in the corpus', function () {
    expect(count(neutralityViolationCorpus()))->toBeGreaterThanOrEqual(50);
});
