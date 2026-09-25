<?php

use App\Drivers\Signing\SigningService;

function makeSigningService(): SigningService
{
    return new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
}

it('signs and verifies a round trip', function () {
    $signing = makeSigningService();
    $kid = $signing->generateKey();

    $jws = $signing->sign(['type' => 'verified_experience', 'business_id' => 42]);

    expect($jws)->toContain('.')
        ->and($signing->verify($jws))->toBe(['type' => 'verified_experience', 'business_id' => 42]);

    // JWS header carries the key ID that produced it.
    [$header] = explode('.', $jws);
    $decoded = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
    expect($decoded)->toBe(['alg' => 'EdDSA', 'kid' => $kid]);
});

it('rejects a tampered payload', function () {
    $signing = makeSigningService();
    $signing->generateKey();

    [$header, , $signature] = explode('.', $signing->sign(['amount' => 100]));
    $tamperedBody = rtrim(strtr(base64_encode(json_encode(['amount' => 999999])), '+/', '-_'), '=');

    $signing->verify("{$header}.{$tamperedBody}.{$signature}");
})->throws(RuntimeException::class, 'signature verification failed');

it('rejects a JWS signed by an unknown key', function () {
    $a = makeSigningService();
    $a->generateKey();
    $jws = $a->sign(['x' => 1]);

    $b = makeSigningService();
    $b->generateKey();

    $b->verify($jws);
})->throws(RuntimeException::class, 'Unknown signing key');

it('exposes only public keys via publicKeys()', function () {
    $signing = makeSigningService();
    $kid = $signing->generateKey();

    $keys = $signing->publicKeys();

    expect($keys)->toHaveCount(1)
        ->and($keys[0]['kid'])->toBe($kid)
        ->and($keys[0])->not->toHaveKey('secret_key');
});

it('uses the pinned active key when set, not just the first one', function () {
    $keysPath = storage_path('framework/testing/signing-'.uniqid().'.json');
    $signing = new SigningService(keysPath: $keysPath);
    $signing->generateKey();
    $second = $signing->generateKey();

    $pinned = new SigningService(keysPath: $keysPath, activeKid: $second);
    $jws = $pinned->sign(['x' => 1]);

    [$header] = explode('.', $jws);
    $decoded = json_decode(base64_decode(strtr($header, '-_', '+/')), true);
    expect($decoded['kid'])->toBe($second);
});

it('is bound in the container and resolves from config', function () {
    $signing = app(SigningService::class);

    expect($signing)->toBeInstanceOf(SigningService::class);
});
