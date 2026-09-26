<?php

namespace App\Domain\Businesses;

/**
 * FR-017-01's plan tiers, introduced early by spec 005 (FR-005-20) because
 * invitation limits need something to key off before 017 (Plans & Billing)
 * exists. Every Business starts on Free (Business::$attributes).
 */
enum BusinessPlan: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Enterprise = 'enterprise';
}
