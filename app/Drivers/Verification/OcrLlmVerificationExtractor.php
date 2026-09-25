<?php

namespace App\Drivers\Verification;

use App\Drivers\Verification\Contracts\VerificationExtractor;
use RuntimeException;

/**
 * VERIFICATION_EXTRACTOR=ocr_llm (plan D12, production): pdftotext for text
 * PDFs and Tesseract OCR for photos, then the AI extracts merchant, date,
 * reference, and amount with confidence scores. Built out when spec 004
 * (Verification) is implemented (build order step 5) — poppler-utils and
 * tesseract-ocr are already in the base image (docker/Dockerfile).
 */
class OcrLlmVerificationExtractor implements VerificationExtractor
{
    public function extract(string $filePath, array $context = []): ExtractionResult
    {
        throw new RuntimeException('OcrLlmVerificationExtractor is not implemented yet — see spec 004.');
    }
}
