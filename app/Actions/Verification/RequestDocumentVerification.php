<?php

namespace App\Actions\Verification;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Verification\ContainsPan;
use App\Domain\Verification\EvaluateAutoApproval;
use App\Domain\Verification\EvaluateTamperSignals;
use App\Domain\Verification\InspectPdf;
use App\Domain\Verification\MerchantMatch;
use App\Domain\Verification\PerceptualHash;
use App\Domain\Verification\ProofFileType;
use App\Domain\Verification\ProofFingerprint;
use App\Domain\Verification\SniffProofFileType;
use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Drivers\Verification\Contracts\VerificationExtractor;
use App\Drivers\Verification\ExtractionResult;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * FR-004-02 through FR-004-11: the document proof upload path. Runs the
 * same auto-approval evaluation (T2) that reference matching (T6) will
 * eventually reuse, and issues an attestation (T4) immediately on
 * approval — same as any other approval path, since IssueAttestation is
 * the single choke point for that.
 */
class RequestDocumentVerification
{
    private const MAX_FILES = 3;

    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

    /**
     * Distance out of 64 bits below which two images count as a
     * near-duplicate (FR-004-07).
     */
    private const NEAR_DUPLICATE_HAMMING_THRESHOLD = 5;

    public function __construct(
        private readonly MalwareScanner $scanner,
        private readonly VerificationExtractor $extractor,
        private readonly IssueAttestation $issueAttestation,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, Review $review, array $files, bool $isRefundOrCancellation = false): ReviewVerification
    {
        $this->guardAuthorAndPublished($actor, $review);
        $this->guardFileCountAndSize($files);

        $types = array_map(fn (UploadedFile $file) => $this->guardAndSniffType($file), $files);

        $paths = [];

        foreach ($files as $file) {
            $path = 'verification-proofs/'.$review->id.'/'.Str::random(20).'.'.($file->getClientOriginalExtension() ?: 'bin');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            $paths[] = $path;
        }

        // MVP scope: extraction, the perceptual hash, and the EXIF tamper
        // check all run on the first file only — up to 3 are accepted
        // (e.g. front/back of a document) but only one is the proof of
        // record for matching and fingerprinting.
        $primaryType = $types[0];
        $primaryPath = $files[0]->getRealPath();

        $business = $review->business;

        $extraction = $this->extractor->extract($primaryPath, [
            'business_name' => $business->name,
            'experience_date_from' => $review->date_of_experience->toDateString(),
            'experience_date_to' => $review->date_of_experience->copy()->addDays(30)->toDateString(),
        ]);

        if ($this->containsPan($extraction)) {
            Storage::disk('local')->delete($paths);

            throw ValidationException::withMessages([
                'proofs' => "This file looks like it contains a full card number and can't be stored — please redact it and upload again.",
            ]);
        }

        $perceptualHash = in_array($primaryType, [ProofFileType::Jpeg, ProofFileType::Png], true)
            ? $this->safePerceptualHash($primaryPath)
            : null;

        $exifSoftwareTag = $primaryType === ProofFileType::Jpeg ? $this->exifSoftwareTag($primaryPath) : null;

        $transactionDate = $extraction->date !== null ? Carbon::instance($extraction->date) : null;

        $extractedFields = [
            'merchant' => $extraction->merchant,
            'date' => $transactionDate?->toDateString(),
            'amount_minor_units' => $extraction->amountMinorUnits,
            'currency' => $extraction->currency,
            'confidence' => $extraction->confidence,
            'perceptual_hash' => $perceptualHash,
            'proof_subtype' => $isRefundOrCancellation ? 'refund' : null,
        ];

        $verification = new ReviewVerification([
            'method' => VerificationMethod::DocumentProof,
            'reference_number' => $extraction->reference,
            'extracted_fields' => $extractedFields,
            'confidence' => $extraction->confidence,
            'proof_paths' => $paths,
        ]);
        $verification->review()->associate($review);

        if ($extraction->reference === null) {
            $extractedFields['auto_approval_hold_reason'] = 'no_reference_extracted';
            $verification->extracted_fields = $extractedFields;
            $verification->save();

            return $verification;
        }

        $fingerprint = $perceptualHash !== null
            ? ProofFingerprint::forDocument($business->id, $extraction->reference, $perceptualHash)
            : ProofFingerprint::forReference($business->id, $extraction->reference);

        $reused = ReviewVerification::where('proof_fingerprint', $fingerprint)->first();

        if ($reused !== null) {
            $this->flagFingerprintReuse($actor, $reused);

            $extractedFields['reused_fingerprint'] = $fingerprint;
            $verification->forceFill([
                'status' => VerificationStatus::Rejected,
                'extracted_fields' => $extractedFields,
                'decided_at' => now(),
                'decision_reason_code' => 'reference_reused',
            ]);
            $verification->save();

            return $verification;
        }

        $merchantCandidates = $this->merchantCandidates($review);
        $merchantMatches = MerchantMatch::matches($extraction->merchant ?? '', $merchantCandidates);

        $hasNearDuplicate = $perceptualHash !== null
            && $this->hasNearDuplicateAcrossOtherAccounts($actor, $perceptualHash);

        $tamperSignals = (new EvaluateTamperSignals)->handle($exifSoftwareTag, $hasNearDuplicate, $perceptualHash);

        $outcome = (new EvaluateAutoApproval)->handle(
            $merchantMatches,
            $transactionDate,
            $review->date_of_experience,
            referenceAlreadyUsed: false,
            tamperSignals: $tamperSignals,
        );

        $verification->proof_fingerprint = $fingerprint;

        if (! $outcome->approved) {
            $extractedFields['auto_approval_hold_reason'] = $outcome->reasonCode;
        }

        $verification->extracted_fields = $extractedFields;
        $verification->save();

        if ($outcome->approved) {
            $this->issueAttestation->handle($verification);
        }

        return $verification->fresh();
    }

    /**
     * @throws AuthorizationException
     */
    private function guardAuthorAndPublished(User $actor, Review $review): void
    {
        if ($review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the author can request verification for this review.');
        }

        // Edge case: "Reviewer tries to verify a review that is held or
        // removed: not allowed until it is published."
        if ($review->trashed() || $review->status !== ReviewStatus::Published) {
            throw ValidationException::withMessages(['review' => 'This review must be published before it can be verified.']);
        }
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    private function guardFileCountAndSize(array $files): void
    {
        if ($files === []) {
            throw ValidationException::withMessages(['proofs' => 'Please upload at least one file.']);
        }

        if (count($files) > self::MAX_FILES) {
            throw ValidationException::withMessages(['proofs' => 'You can upload at most 3 files.']);
        }

        foreach ($files as $file) {
            if ($file->getSize() === 0) {
                throw ValidationException::withMessages(['proofs' => 'One of the files is empty.']);
            }

            if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
                throw ValidationException::withMessages(['proofs' => 'Each file must be 10 MB or smaller.']);
            }
        }
    }

    private function guardAndSniffType(UploadedFile $file): ProofFileType
    {
        $type = SniffProofFileType::detect($file->getRealPath());

        if ($type === null) {
            throw ValidationException::withMessages([
                'proofs' => 'One of the files is not a supported proof type (PDF, JPEG, PNG, HEIC, or .eml).',
            ]);
        }

        $scan = $this->scanner->scan($file->getRealPath());

        if (! $scan->clean) {
            throw ValidationException::withMessages(['proofs' => 'One of the files failed a malware scan.']);
        }

        if ($type === ProofFileType::Pdf) {
            if (! InspectPdf::isReadable($file->getRealPath())) {
                throw ValidationException::withMessages(['proofs' => 'One of the PDF files is corrupt or unreadable.']);
            }

            if (InspectPdf::isPasswordProtected($file->getRealPath())) {
                throw ValidationException::withMessages(['proofs' => 'Please remove the password from the PDF and upload it again.']);
            }
        }

        return $type;
    }

    private function containsPan(ExtractionResult $extraction): bool
    {
        $text = implode(' ', array_filter([
            $extraction->merchant,
            $extraction->reference,
            $extraction->amountMinorUnits !== null ? (string) $extraction->amountMinorUnits : null,
        ]));

        return $text !== '' && ContainsPan::check($text);
    }

    private function safePerceptualHash(string $filePath): ?string
    {
        try {
            return PerceptualHash::compute($filePath);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function exifSoftwareTag(string $filePath): ?string
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $exif = @exif_read_data($filePath);

        return is_array($exif) ? ($exif['Software'] ?? null) : null;
    }

    /**
     * @return list<string>
     */
    private function merchantCandidates(Review $review): array
    {
        $candidates = [
            $review->business->name,
            $review->business->primary_domain,
            ...($review->business->additional_domains ?? []),
        ];

        if ($review->taggedBusiness !== null) {
            $candidates = [
                ...$candidates,
                $review->taggedBusiness->name,
                $review->taggedBusiness->primary_domain,
                ...($review->taggedBusiness->additional_domains ?? []),
            ];
        }

        return array_values(array_filter($candidates));
    }

    private function hasNearDuplicateAcrossOtherAccounts(User $actor, string $perceptualHash): bool
    {
        $candidates = ReviewVerification::query()
            ->whereHas('review', fn ($query) => $query->where('reviewer_id', '!=', $actor->id))
            ->whereNotNull('extracted_fields')
            ->get(['extracted_fields']);

        foreach ($candidates as $candidate) {
            $candidateHash = $candidate->extracted_fields['perceptual_hash'] ?? null;

            if ($candidateHash === null) {
                continue;
            }

            if (PerceptualHash::hammingDistance($perceptualHash, $candidateHash) <= self::NEAR_DUPLICATE_HAMMING_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    /**
     * FR-004-11: "rejected and sent to fraud signals (006)." 006 doesn't
     * exist yet, so this is the same honest placeholder tasks.md's
     * preamble describes elsewhere — a real log line today, a real queue
     * once 006 is built.
     */
    private function flagFingerprintReuse(User $actor, ReviewVerification $original): void
    {
        Log::warning('Proof fingerprint reuse detected (FR-004-11)', [
            'new_reviewer_id' => $actor->id,
            'original_reviewer_id' => $original->review->reviewer_id,
            'original_verification_id' => $original->id,
        ]);
    }
}
