<?php

namespace App\Domain\Moderation;

/**
 * FR-006-06: the three anomaly shapes staff are alerted to on a Business.
 */
enum IncidentType: string
{
    case ReviewSpike = 'review_spike';
    case RatingShift = 'rating_shift';
    case NewAccountCluster = 'new_account_cluster';
}
