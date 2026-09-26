<?php

namespace App\Domain\Businesses;

/**
 * FR-006-14 step 4: the enforcement ladder's feature-restriction step.
 * `RestrictBusinessFeature` sets these on `businesses.restricted_features`.
 * Only `Flagging` is wired into an actual check today (`CreateFlag`) —
 * invitations (005) and profile edits (002) are honest gaps until those
 * specs' own actions check `Business::hasFeatureRestricted()` too.
 */
enum RestrictableFeature: string
{
    case Flagging = 'flagging';
    case Invitations = 'invitations';
    case ProfileEdits = 'profile_edits';
}
