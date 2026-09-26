<?php

use App\Domain\Verification\ProofFileType;
use App\Domain\Verification\SniffProofFileType;

function writeTempFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'sniff');
    file_put_contents($path, $contents);

    return $path;
}

it('detects a PDF by its magic bytes (FR-004-04)', function () {
    $path = writeTempFile("%PDF-1.7\n%content here\n%%EOF");

    expect(SniffProofFileType::detect($path))->toBe(ProofFileType::Pdf);
});

it('detects a JPEG by its magic bytes', function () {
    $path = writeTempFile("\xFF\xD8\xFF\xE0rest of a jpeg");

    expect(SniffProofFileType::detect($path))->toBe(ProofFileType::Jpeg);
});

it('detects a PNG by its magic bytes', function () {
    $path = writeTempFile("\x89PNG\r\n\x1a\nrest of a png");

    expect(SniffProofFileType::detect($path))->toBe(ProofFileType::Png);
});

it('detects a HEIC by its ftyp box brand', function () {
    $path = writeTempFile("\x00\x00\x00\x18ftypheic\x00\x00\x00\x00rest");

    expect(SniffProofFileType::detect($path))->toBe(ProofFileType::Heic);
});

it('does not mistake an unrelated ftyp brand for HEIC', function () {
    $path = writeTempFile("\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00rest");

    expect(SniffProofFileType::detect($path))->toBeNull();
});

it('detects an .eml by its RFC 822 headers', function () {
    $path = writeTempFile("Return-Path: <a@b.com>\r\nFrom: a@b.com\r\nSubject: Your receipt\r\n\r\nBody text.");

    expect(SniffProofFileType::detect($path))->toBe(ProofFileType::Eml);
});

it('does not mistake plain binary data for an .eml', function () {
    $path = writeTempFile(random_bytes(64));

    expect(SniffProofFileType::detect($path))->not->toBe(ProofFileType::Eml);
});

it('returns null when a renamed extension does not match its real content (FR-004-04)', function () {
    $path = writeTempFile('just some plain text, not a real proof file');

    expect(SniffProofFileType::detect($path))->toBeNull();
});

it('returns null for an empty file', function () {
    $path = writeTempFile('');

    expect(SniffProofFileType::detect($path))->toBeNull();
});
