<?php

namespace App\Domain\Businesses;

/**
 * FR-002-11.
 */
enum ClaimMethod: string
{
    case Email = 'email';
    case DnsTxt = 'dns_txt';
    case HtmlFile = 'html_file';
    case Manual = 'manual';
}
