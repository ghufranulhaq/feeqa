<?php

use App\Support\Reviews\ReviewText;

it('strips script and style blocks entirely, including their contents (edge case: pasted HTML or scripts)', function () {
    $raw = '<script>alert("x")</script><style>body{color:red}</style>Great trip overall.';

    expect(ReviewText::sanitize($raw))->toBe('Great trip overall.');
});

it('strips ordinary markup but keeps the text and decodes entities', function () {
    $raw = '<p>Loved the <b>hotel</b> &mdash; would &amp; will book again.</p>';

    expect(ReviewText::sanitize($raw))->toBe('Loved the hotel — would & will book again.');
});

it('trims surrounding whitespace after stripping', function () {
    expect(ReviewText::sanitize("   <div>Fine</div>   \n"))->toBe('Fine');
});

it('linkifies only exact occurrences of the reviewed business\'s own domain', function () {
    $text = 'Booked through skyhop-travel.com, also saw evil-skyhop-travel.com and skyhop-travel.com.evil.com mentioned.';

    $html = ReviewText::linkifyOwnDomain($text, 'skyhop-travel.com');

    expect($html)
        ->toContain('<a href="https://skyhop-travel.com" rel="nofollow noopener" target="_blank">skyhop-travel.com</a>')
        ->and(substr_count($html, '<a '))->toBe(1)
        ->and($html)->toContain('evil-skyhop-travel.com')
        ->and($html)->toContain('skyhop-travel.com.evil.com');
});

it('escapes everything else so no other markup or link becomes clickable', function () {
    $text = 'Visit <a href="https://phishing.example">phishing.example</a> or javascript:alert(1) for a "deal".';

    $html = ReviewText::linkifyOwnDomain($text, 'skyhop-travel.com');

    expect($html)
        ->not->toContain('<a href="https://phishing.example"')
        ->toContain('&lt;a href=')
        ->toContain('&quot;deal&quot;');
});

it('returns escaped text unchanged when the business has no domain', function () {
    $html = ReviewText::linkifyOwnDomain('Plain <b>text</b>.', '');

    expect($html)->toBe('Plain &lt;b&gt;text&lt;/b&gt;.');
});
