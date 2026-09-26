<?php

namespace App\Domain\Verification;

/**
 * FR-004-01, FR-004-04: the file types document proof accepts.
 */
enum ProofFileType: string
{
    case Pdf = 'pdf';
    case Jpeg = 'jpeg';
    case Png = 'png';
    case Heic = 'heic';
    case Eml = 'eml';
}
