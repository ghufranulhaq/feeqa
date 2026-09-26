<?php

namespace App\Domain\Moderation;

/**
 * FR-006-02: the platform-wide list of prohibited-content categories.
 * Used on flags (T4), staff moderation actions and their statements of
 * reasons (T5), and the enforcement ladder (T6) — one vocabulary for all
 * three, rather than a separate reason list per feature.
 */
enum ReasonCode: string
{
    case HarmfulIllegal = 'harmful_illegal';
    case PersonalInfo = 'personal_info';
    case AdvertisingSpam = 'advertising_spam';
    case NotGenuine = 'not_genuine';
    case Incentivised = 'incentivised';
    case ConflictOfInterest = 'conflict_of_interest';
    case WrongBusiness = 'wrong_business';
    case OffTopic = 'off_topic';
    case AiGeneratedDeceptive = 'ai_generated_deceptive';
    case IpInfringement = 'ip_infringement';
    case OtherIllegal = 'other_illegal';
}
