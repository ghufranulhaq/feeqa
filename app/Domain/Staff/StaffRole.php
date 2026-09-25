<?php

namespace App\Domain\Staff;

/**
 * FR-001-14.
 */
enum StaffRole: string
{
    case Moderator = 'Moderator';
    case SeniorModerator = 'Senior Moderator';
    case Mediator = 'Mediator';
    case Support = 'Support';
    case Admin = 'Admin';
}
