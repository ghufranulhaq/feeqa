<?php

namespace App\Drivers\Signing;

use RuntimeException;

/**
 * Signed attestations (plan D16): Ed25519 via PHP's built-in sodium
 * extension, in compact JWS format (RFC 7515) with key IDs. Keys live in a
 * JSON file on the storage volume (platform.signing.keys_path). Public
 * keys only are meant to be published at /.well-known/platform-keys.json
 * (route added when a feature first needs it — spec 004/014).
 *
 * Not driver-switchable like the others (constitution §5.1): there is no
 * "fake" signing, since a signature either verifies or it doesn't.
 */
class SigningService
{
    /** @var array<string, array{public_key: string, secret_key: string}>|null */
    private ?array $keys = null;

    public function __construct(
        private readonly string $keysPath,
        private readonly ?string $activeKid = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): string
    {
        $kid = $this->activeKid ?? $this->firstKeyId();
        $key = $this->key($kid);

        $header = $this->base64UrlEncode(json_encode(['alg' => 'EdDSA', 'kid' => $kid], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = sodium_crypto_sign_detached("{$header}.{$body}", base64_decode($key['secret_key']));

        return "{$header}.{$body}.".$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $jws): array
    {
        [$header, $body, $signature] = array_pad(explode('.', $jws, 3), 3, null);

        if ($header === null || $body === null || $signature === null) {
            throw new RuntimeException('Malformed JWS: expected header.payload.signature.');
        }

        $decodedHeader = json_decode($this->base64UrlDecode($header), true, flags: JSON_THROW_ON_ERROR);
        $kid = $decodedHeader['kid'] ?? throw new RuntimeException('JWS header is missing "kid".');
        $key = $this->key($kid);

        $verified = sodium_crypto_sign_verify_detached(
            $this->base64UrlDecode($signature),
            "{$header}.{$body}",
            base64_decode($key['public_key']),
        );

        if (! $verified) {
            throw new RuntimeException('JWS signature verification failed.');
        }

        return json_decode($this->base64UrlDecode($body), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Public keys only, for /.well-known/platform-keys.json.
     *
     * @return list<array{kid: string, alg: string, public_key: string}>
     */
    public function publicKeys(): array
    {
        $result = [];

        foreach ($this->allKeys() as $kid => $key) {
            $result[] = ['kid' => $kid, 'alg' => 'EdDSA', 'public_key' => $key['public_key']];
        }

        return $result;
    }

    /**
     * Generates a new Ed25519 keypair and appends it to the keys file
     * (creating it if needed). Returns the new key ID.
     */
    public function generateKey(): string
    {
        $keypair = sodium_crypto_sign_keypair();
        $kid = 'key-'.substr(hash('sha256', $keypair), 0, 12);

        $keys = $this->allKeys();
        $keys[$kid] = [
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'secret_key' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
        ];

        $this->writeKeys($keys);
        $this->keys = $keys;

        return $kid;
    }

    /**
     * @return array{public_key: string, secret_key: string}
     */
    private function key(string $kid): array
    {
        return $this->allKeys()[$kid] ?? throw new RuntimeException("Unknown signing key \"{$kid}\".");
    }

    private function firstKeyId(): string
    {
        $keys = $this->allKeys();

        if ($keys === []) {
            throw new RuntimeException('No signing keys exist yet — call generateKey() first.');
        }

        return array_key_first($keys);
    }

    /**
     * @return array<string, array{public_key: string, secret_key: string}>
     */
    private function allKeys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        if (! is_file($this->keysPath)) {
            return $this->keys = [];
        }

        return $this->keys = json_decode(file_get_contents($this->keysPath), true, flags: JSON_THROW_ON_ERROR) ?? [];
    }

    /**
     * @param  array<string, array{public_key: string, secret_key: string}>  $keys
     */
    private function writeKeys(array $keys): void
    {
        $directory = dirname($this->keysPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0700, recursive: true);
        }

        file_put_contents($this->keysPath, json_encode($keys, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        chmod($this->keysPath, 0600);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
