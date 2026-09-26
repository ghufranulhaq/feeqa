<?php

use App\Support\Environment;

it('allows demo relaxations everywhere except production', function (string $env, bool $allowed) {
    app()['env'] = $env;

    expect(Environment::demoRelaxationsAllowed())->toBe($allowed);
})->with([
    ['local', true],
    ['testing', true],
    ['demo', true],
    ['production', false],
]);

it('requires a 12-character password in production and 6 elsewhere', function (string $env, int $length) {
    app()['env'] = $env;

    expect(Environment::passwordMinLength())->toBe($length);
})->with([
    ['local', 6],
    ['testing', 6],
    ['demo', 6],
    ['production', 12],
]);

it('enforces the staff IP allow-list only in production', function (string $env, bool $enforced) {
    app()['env'] = $env;

    expect(Environment::staffIpAllowlistEnforced())->toBe($enforced);
})->with([
    ['local', false],
    ['demo', false],
    ['production', true],
]);

it('always checks breached passwords in production, whatever .env says (FR-001-04)', function () {
    app()['env'] = 'production';
    config(['platform.security.check_breached_passwords' => false]);

    expect(Environment::checkBreachedPasswords())->toBeTrue();
});

it('follows the config toggle outside production', function (string $env, bool $configured) {
    app()['env'] = $env;
    config(['platform.security.check_breached_passwords' => $configured]);

    expect(Environment::checkBreachedPasswords())->toBe($configured);
})->with([
    ['local', true],
    ['local', false],
    ['testing', false],
    ['demo', true],
]);

it('always keeps lifecycle-update windows real in production, whatever .env says (constitution §5.6)', function () {
    app()['env'] = 'production';
    config(['platform.reviews.lifecycle_updates.always_open_windows' => true]);

    expect(Environment::lifecycleUpdateWindowsAlwaysOpen())->toBeFalse();
});

it('follows the lifecycle-update-windows config toggle outside production', function (string $env, bool $configured) {
    app()['env'] = $env;
    config(['platform.reviews.lifecycle_updates.always_open_windows' => $configured]);

    expect(Environment::lifecycleUpdateWindowsAlwaysOpen())->toBe($configured);
})->with([
    ['local', true],
    ['local', false],
    ['testing', false],
    ['demo', true],
]);

it('always deletes raw proof files on schedule in production, whatever .env says (constitution §5.6)', function () {
    app()['env'] = 'production';
    config(['platform.verification.delete_raw_proof_files_on_schedule' => false]);

    expect(Environment::rawProofFilesDeletedOnSchedule())->toBeTrue();
});

it('follows the raw-proof-file-deletion config toggle outside production', function (string $env, bool $configured) {
    app()['env'] = $env;
    config(['platform.verification.delete_raw_proof_files_on_schedule' => $configured]);

    expect(Environment::rawProofFilesDeletedOnSchedule())->toBe($configured);
})->with([
    ['local', true],
    ['local', false],
    ['testing', false],
    ['demo', true],
]);

it('does nothing outside production, even with fake drivers configured', function () {
    app()['env'] = 'demo';
    config(['platform.ai.driver' => 'fake']);

    Environment::assertProductionIsSafe();
})->throwsNoExceptions();

it('refuses to boot in production if any driver is still fake', function (string $key) {
    app()['env'] = 'production';
    config([
        'platform.ai.driver' => 'openai-compatible',
        'platform.verification.extractor' => 'ocr_llm',
        'platform.transcription.driver' => 'some-provider',
        'platform.malware.scanner' => 'clamav',
        'platform.billing.driver' => 'stripe',
        $key => 'fake',
    ]);

    Environment::assertProductionIsSafe();
})->with([
    'platform.ai.driver',
    'platform.verification.extractor',
    'platform.transcription.driver',
    'platform.malware.scanner',
    'platform.billing.driver',
])->throws(RuntimeException::class);

it('boots fine in production when every driver is real', function () {
    app()['env'] = 'production';
    config([
        'platform.ai.driver' => 'openai-compatible',
        'platform.verification.extractor' => 'ocr_llm',
        'platform.transcription.driver' => 'some-provider',
        'platform.malware.scanner' => 'clamav',
        'platform.billing.driver' => 'stripe',
    ]);

    Environment::assertProductionIsSafe();
})->throwsNoExceptions();
