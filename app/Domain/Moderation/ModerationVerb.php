<?php

namespace App\Domain\Moderation;

/**
 * FR-006-12: the staff verbs `ModerateReview` covers today. The full list
 * also includes block account, restrict business feature, apply/lift a
 * Consumer Warning, merge duplicates, and freeze reviews — each of those
 * either already exists (`FreezeBusinessReviews`, T3) or is its own
 * action (`BlockUserAccount`, `RestrictBusinessFeature`) because it
 * doesn't target a single review the way these five do. Merge duplicates
 * has no home yet — no spec describes what a "duplicate review" merge
 * means structurally — so it's left out rather than guessed at.
 */
enum ModerationVerb: string
{
    case Publish = 'publish';
    case Remove = 'remove';
    case Redact = 'redact';
    case MarkNotGenuine = 'mark_not_genuine';
    case RequestVerification = 'request_verification';
}
