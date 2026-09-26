<?php

namespace App\Domain\Moderation;

/**
 * FR-006-14's business ladder (6 steps) and FR-006-15's reviewer ladder (3
 * steps) share their first two steps' wording ("educational notice" and
 * a plain "warning") — one enum, two ordered sequences, rather than
 * duplicating those two cases per ladder.
 */
enum EnforcementStep: string
{
    // Business ladder only.
    case EducationalNotice = 'educational_notice';
    case Warning = 'warning';
    case FinalNotice = 'final_notice';
    case FeatureRestriction = 'feature_restriction';
    case ConsumerWarning = 'consumer_warning';
    case Termination = 'termination';

    // Reviewer ladder only.
    case AccountBlock = 'account_block';

    /**
     * @return list<self>
     */
    public static function sequenceFor(EnforcementLadder $ladder): array
    {
        return match ($ladder) {
            EnforcementLadder::Business => [
                self::EducationalNotice,
                self::Warning,
                self::FinalNotice,
                self::FeatureRestriction,
                self::ConsumerWarning,
                self::Termination,
            ],
            EnforcementLadder::Reviewer => [
                self::EducationalNotice,
                self::Warning,
                self::AccountBlock,
            ],
        };
    }
}
