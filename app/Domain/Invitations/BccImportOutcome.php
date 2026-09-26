<?php

namespace App\Domain\Invitations;

/**
 * FR-005-06, edge case table: a dropped email is never an error, just one
 * of these two non-`Imported` outcomes, each counted on the Business's own
 * diagnostics columns rather than raised as an exception.
 */
enum BccImportOutcome: string
{
    case Imported = 'imported';
    case FailedAlignment = 'failed_alignment';
    case NoReferenceMatch = 'no_reference_match';
}
