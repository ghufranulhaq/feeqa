<?php

namespace App\Domain\Businesses;

/**
 * FR-002-25. Used by spec 013 to decide whether insider reviews are
 * allowed (>= 50).
 */
enum EmployeeSizeBand: string
{
    case Under50 = '<50';
    case From50To249 = '50-249';
    case From250To999 = '250-999';
    case OneThousandPlus = '1000+';
    case Unknown = 'unknown';
}
