<?php

use App\Domain\Verification\InspectPdf;

function writePdf(string $body): string
{
    $path = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($path, $body);

    return $path;
}

it('treats a well-formed PDF trailer as readable', function () {
    $path = writePdf("%PDF-1.7\n1 0 obj<< >>endobj\ntrailer<< /Root 1 0 R >>\nstartxref\n123\n%%EOF");

    expect(InspectPdf::isReadable($path))->toBeTrue()
        ->and(InspectPdf::isPasswordProtected($path))->toBeFalse();
});

it('flags a PDF with an /Encrypt dictionary as password-protected', function () {
    $path = writePdf("%PDF-1.7\ntrailer<< /Root 1 0 R /Encrypt 2 0 R >>\nstartxref\n123\n%%EOF");

    expect(InspectPdf::isPasswordProtected($path))->toBeTrue();
});

it('treats a file missing the PDF header as unreadable (edge case: corrupt upload)', function () {
    $path = writePdf('this is not a pdf at all');

    expect(InspectPdf::isReadable($path))->toBeFalse();
});

it('treats a truncated file missing its trailer as unreadable', function () {
    $path = writePdf('%PDF-1.7'."\n".'1 0 obj<< >>endobj');

    expect(InspectPdf::isReadable($path))->toBeFalse();
});

it('treats an empty file as unreadable (edge case: empty upload)', function () {
    $path = writePdf('');

    expect(InspectPdf::isReadable($path))->toBeFalse();
});
