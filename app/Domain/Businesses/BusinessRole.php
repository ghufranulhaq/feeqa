<?php

namespace App\Domain\Businesses;

/**
 * FR-001-09. Prefixed "Business " in the underlying Spatie role name (see
 * BusinessRolesSeeder) so these never collide with the staff role set
 * (spec 001 T17), which reuses some of the same words (e.g. "Admin").
 */
enum BusinessRole: string
{
    case Owner = 'Business Owner';
    case Admin = 'Business Admin';
    case Responder = 'Business Responder';
    case Analyst = 'Business Analyst';

    /**
     * @return list<BusinessPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [
                BusinessPermission::EditProfile,
                BusinessPermission::ReplyToReviewsAndCases,
                BusinessPermission::FlagReviews,
                BusinessPermission::SendInvitations,
                BusinessPermission::ManageIntegrations,
                BusinessPermission::ViewAnalytics,
                BusinessPermission::ManageMembers,
                BusinessPermission::ManageBilling,
                BusinessPermission::TransferOrDeleteBusiness,
            ],
            self::Admin => [
                BusinessPermission::EditProfile,
                BusinessPermission::ReplyToReviewsAndCases,
                BusinessPermission::FlagReviews,
                BusinessPermission::SendInvitations,
                BusinessPermission::ManageIntegrations,
                BusinessPermission::ViewAnalytics,
                BusinessPermission::ManageMembers,
            ],
            self::Responder => [
                BusinessPermission::ReplyToReviewsAndCases,
                BusinessPermission::FlagReviews,
                BusinessPermission::ViewAnalytics,
            ],
            self::Analyst => [
                BusinessPermission::ViewAnalytics,
            ],
        };
    }
}
