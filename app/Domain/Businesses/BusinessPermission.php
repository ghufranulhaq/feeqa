<?php

namespace App\Domain\Businesses;

/**
 * FR-001-10's permission matrix, one case per row/capability. Values are
 * the underlying Spatie permission names.
 */
enum BusinessPermission: string
{
    case EditProfile = 'business.edit-profile';
    case ReplyToReviewsAndCases = 'business.reply-to-reviews-and-cases';
    case FlagReviews = 'business.flag-reviews';
    case SendInvitations = 'business.send-invitations';
    case ManageIntegrations = 'business.manage-integrations';
    case ViewAnalytics = 'business.view-analytics';
    case ManageMembers = 'business.manage-members';
    case ManageBilling = 'business.manage-billing';
    case TransferOrDeleteBusiness = 'business.transfer-or-delete-business';
}
