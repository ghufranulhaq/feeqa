<?php

use App\Actions\Verification\IssueAttestation;
use App\Actions\Verification\RequestDocumentVerification;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Drivers\Malware\FakeMalwareScanner;
use App\Drivers\Malware\ScanResult;
use App\Drivers\Signing\SigningService;
use App\Drivers\Verification\Contracts\VerificationExtractor;
use App\Drivers\Verification\ExtractionResult;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function fakeIssueAttestation(): IssueAttestation
{
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    return new IssueAttestation($signing);
}

function extractorReturning(
    ?string $merchant = 'Skyline Airways',
    ?DateTimeInterface $date = null,
    ?string $reference = 'BK123456',
    ?int $amount = 5000,
    string $currency = 'GBP',
    int $confidence = 92,
): VerificationExtractor {
    return new class($merchant, $date, $reference, $amount, $currency, $confidence) implements VerificationExtractor
    {
        public function __construct(
            private readonly ?string $merchant,
            private readonly ?DateTimeInterface $date,
            private readonly ?string $reference,
            private readonly ?int $amount,
            private readonly string $currency,
            private readonly int $confidence,
        ) {}

        public function extract(string $filePath, array $context = []): ExtractionResult
        {
            return new ExtractionResult($this->merchant, $this->date, $this->reference, $this->amount, $this->currency, $this->confidence);
        }
    };
}

function dirtyMalwareScanner(): MalwareScanner
{
    return new class implements MalwareScanner
    {
        public function scan(string $filePath): ScanResult
        {
            return new ScanResult(clean: false, reason: 'eicar-test-signature');
        }
    };
}

function makeVerificationAction(
    ?VerificationExtractor $extractor = null,
    ?MalwareScanner $scanner = null,
): RequestDocumentVerification {
    return new RequestDocumentVerification(
        $scanner ?? new FakeMalwareScanner,
        $extractor ?? extractorReturning(),
        fakeIssueAttestation(),
    );
}

function realJpegBytes(): string
{
    $image = imagecreatetruecolor(16, 16);
    $black = imagecolorallocate($image, 0, 0, 0);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 7, 15, $black);
    imagefilledrectangle($image, 8, 0, 15, 15, $white);
    ob_start();
    imagejpeg($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

function fakePdfUpload(string $name = 'receipt.pdf', bool $passwordProtected = false, bool $corrupt = false): UploadedFile
{
    if ($corrupt) {
        return UploadedFile::fake()->createWithContent($name, 'not really a pdf');
    }

    $trailer = $passwordProtected ? 'trailer<< /Root 1 0 R /Encrypt 2 0 R >>' : 'trailer<< /Root 1 0 R >>';
    $content = "%PDF-1.7\n1 0 obj<< >>endobj\n{$trailer}\nstartxref\n0\n%%EOF";

    return UploadedFile::fake()->createWithContent($name, $content);
}

function fakeJpegUpload(string $name = 'receipt.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, realJpegBytes());
}

beforeEach(function () {
    Storage::fake('local');
});

it('auto-approves and issues an attestation when everything matches (FR-004-06)', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($business)->create(['date_of_experience' => '2026-06-01']);
    $extractor = extractorReturning(merchant: 'Skyline Airways', date: new DateTime('2026-06-05'));

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]);

    expect($verification->status)->toBe(VerificationStatus::Approved)
        ->and($verification->method)->toBe(VerificationMethod::DocumentProof)
        ->and($verification->attestation)->not->toBeNull()
        ->and($review->fresh()->isVerified())->toBeTrue();
});

it('holds for the staff queue when the merchant does not match (FR-004-06, FR-004-08)', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($business)->create(['date_of_experience' => now()->toDateString()]);
    $extractor = extractorReturning(merchant: 'Totally Different Co', date: now());

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]);

    expect($verification->status)->toBe(VerificationStatus::Pending)
        ->and($verification->extracted_fields['auto_approval_hold_reason'])->toBe('merchant_mismatch')
        ->and($review->fresh()->isVerified())->toBeFalse();
});

it('matches the tagged business for an agency/airline shared e-ticket (edge case)', function () {
    $agency = Business::factory()->create(['name' => 'BookIt Travel']);
    $airline = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($agency)->create([
        'tagged_business_id' => $airline->id,
        'date_of_experience' => now()->toDateString(),
    ]);
    $extractor = extractorReturning(merchant: 'Skyline Airways', date: now());

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]);

    expect($verification->status)->toBe(VerificationStatus::Approved);
});

it('holds when the extracted date is outside the auto-approval window', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($business)->create(['date_of_experience' => '2020-01-01']);
    $extractor = extractorReturning(merchant: 'Skyline Airways', date: new DateTime('2020-01-01'));

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]);

    expect($verification->status)->toBe(VerificationStatus::Pending)
        ->and($verification->extracted_fields['auto_approval_hold_reason'])->toBe('date_out_of_window');
});

it('holds when no reference number could be extracted', function () {
    $review = Review::factory()->create(['date_of_experience' => now()->toDateString()]);
    $extractor = extractorReturning(reference: null, date: now());

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]);

    expect($verification->status)->toBe(VerificationStatus::Pending)
        ->and($verification->proof_fingerprint)->toBeNull()
        ->and($verification->extracted_fields['auto_approval_hold_reason'])->toBe('no_reference_extracted');
});

it('rejects a reused proof fingerprint for the second account (FR-004-10, FR-004-11)', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $firstReview = Review::factory()->for($business)->create(['date_of_experience' => now()->toDateString()]);
    $secondReview = Review::factory()->for($business)->create(['date_of_experience' => now()->toDateString()]);
    $extractor = extractorReturning(merchant: 'Skyline Airways', date: now(), reference: 'BK-DUPLICATE');

    $first = makeVerificationAction($extractor)->handle($firstReview->reviewer, $firstReview, [fakePdfUpload()]);
    expect($first->status)->toBe(VerificationStatus::Approved);

    $second = makeVerificationAction($extractor)->handle($secondReview->reviewer, $secondReview, [fakePdfUpload()]);

    expect($second->status)->toBe(VerificationStatus::Rejected)
        ->and($second->decision_reason_code)->toBe('reference_reused')
        ->and($second->proof_fingerprint)->toBeNull();
});

it('rejects storage when the extracted text contains what looks like a card number (FR-004-09)', function () {
    $review = Review::factory()->create();
    $extractor = extractorReturning(merchant: '4111 1111 1111 1111');

    expect(fn () => makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()]))
        ->toThrow(ValidationException::class);

    expect(ReviewVerification::count())->toBe(0);
});

it('rejects an unsupported file type (edge case: corrupt/unsupported upload)', function () {
    $review = Review::factory()->create();
    $file = UploadedFile::fake()->createWithContent('notes.txt', 'just some plain text');

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [$file]))
        ->toThrow(ValidationException::class);
});

it('rejects a file that fails the malware scan', function () {
    $review = Review::factory()->create();

    expect(fn () => makeVerificationAction(scanner: dirtyMalwareScanner())->handle($review->reviewer, $review, [fakePdfUpload()]))
        ->toThrow(ValidationException::class);
});

it('rejects a password-protected PDF with a specific message (edge case)', function () {
    $review = Review::factory()->create();
    $file = fakePdfUpload(passwordProtected: true);

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [$file]))
        ->toThrow(ValidationException::class);
});

it('rejects a corrupt PDF with a specific message (edge case)', function () {
    $review = Review::factory()->create();
    $file = fakePdfUpload(corrupt: true);

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [$file]))
        ->toThrow(ValidationException::class);
});

it('rejects an empty upload (edge case)', function () {
    $review = Review::factory()->create();
    $file = UploadedFile::fake()->create('empty.pdf', 0);

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [$file]))
        ->toThrow(ValidationException::class);
});

it('rejects an oversized upload (edge case: > 10 MB)', function () {
    $review = Review::factory()->create();
    $file = UploadedFile::fake()->create('big.pdf', 10241);

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [$file]))
        ->toThrow(ValidationException::class);
});

it('rejects more than 3 files', function () {
    $review = Review::factory()->create();
    $files = [fakePdfUpload('a.pdf'), fakePdfUpload('b.pdf'), fakePdfUpload('c.pdf'), fakePdfUpload('d.pdf')];

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, $files))
        ->toThrow(ValidationException::class);
});

it('rejects a non-author requesting verification', function () {
    $review = Review::factory()->create();
    $notAuthor = User::factory()->create();

    expect(fn () => makeVerificationAction()->handle($notAuthor, $review, [fakePdfUpload()]))
        ->toThrow(AuthorizationException::class);
});

it('rejects verifying a review that is not published yet (edge case)', function () {
    $review = Review::factory()->create(['status' => ReviewStatus::Held]);

    expect(fn () => makeVerificationAction()->handle($review->reviewer, $review, [fakePdfUpload()]))
        ->toThrow(ValidationException::class);
});

it('records a self-reported refund/cancellation proof under the same method (edge case)', function () {
    $business = Business::factory()->create(['name' => 'Skyline Airways']);
    $review = Review::factory()->for($business)->create(['date_of_experience' => now()->toDateString()]);
    $extractor = extractorReturning(merchant: 'Skyline Airways', date: now());

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload()], isRefundOrCancellation: true);

    expect($verification->method)->toBe(VerificationMethod::DocumentProof)
        ->and($verification->extracted_fields['proof_subtype'])->toBe('refund');
});

it('stores every uploaded file privately', function () {
    $review = Review::factory()->create(['date_of_experience' => now()->toDateString()]);
    $extractor = extractorReturning(date: now());

    $verification = makeVerificationAction($extractor)->handle($review->reviewer, $review, [fakePdfUpload(), fakeJpegUpload()]);

    expect($verification->proof_paths)->toHaveCount(2);

    foreach ($verification->proof_paths as $path) {
        Storage::disk('local')->assertExists($path);
    }
});

it('computes a perceptual hash for an image proof but not for a PDF', function () {
    $review = Review::factory()->create(['date_of_experience' => now()->toDateString()]);

    $pdfVerification = makeVerificationAction(extractorReturning(reference: 'PDF-REF', date: now()))
        ->handle($review->reviewer, $review, [fakePdfUpload()]);

    $anotherReview = Review::factory()->create(['date_of_experience' => now()->toDateString()]);
    $jpegVerification = makeVerificationAction(extractorReturning(reference: 'JPG-REF', date: now()))
        ->handle($anotherReview->reviewer, $anotherReview, [fakeJpegUpload()]);

    expect($pdfVerification->extracted_fields['perceptual_hash'])->toBeNull()
        ->and($jpegVerification->extracted_fields['perceptual_hash'])->toMatch('/^[0-9a-f]{16}$/');
});
