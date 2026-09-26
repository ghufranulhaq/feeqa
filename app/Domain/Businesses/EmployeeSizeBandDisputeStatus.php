<?php

namespace App\Domain\Businesses;

/**
 * Edge cases table: a business disputing its FR-002-25 employee size band.
 */
enum EmployeeSizeBandDisputeStatus: string
{
    case Pending = 'pending';
    case Upheld = 'upheld';
    case Changed = 'changed';
}
