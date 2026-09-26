<?php

namespace App\Actions\Businesses;

use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;

final class ProfileUpdateResult
{
    public function __construct(
        public readonly Business $business,
        public readonly ?BusinessProfileChangeRequest $changeRequest,
    ) {}
}
