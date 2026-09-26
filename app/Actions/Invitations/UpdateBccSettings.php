<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-005-06, FR-005-07: the two things "business-configurable" means for
 * the BCC method — which extra sender addresses the alignment check
 * trusts alongside the Business's own verified domains, and the regex
 * `ImportBccEmail` uses to find a reference in the subject/body.
 */
class UpdateBccSettings
{
    /**
     * @param  array{registered_senders?: ?array<int, string>, reference_pattern?: ?string}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Business $business, User $actor, array $data): Business
    {
        if (! $business->userCan($actor, BusinessPermission::ManageIntegrations)) {
            throw new AuthorizationException('You cannot change BCC settings for this business.');
        }

        if (array_key_exists('reference_pattern', $data) && $data['reference_pattern'] !== null) {
            $this->guardValidRegex($data['reference_pattern']);
        }

        $business->update([
            'bcc_registered_senders' => $data['registered_senders'] ?? $business->bcc_registered_senders,
            'bcc_reference_pattern' => $data['reference_pattern'] ?? $business->bcc_reference_pattern,
        ]);

        return $business;
    }

    private function guardValidRegex(string $pattern): void
    {
        if (@preg_match($pattern, '') === false) {
            throw ValidationException::withMessages(['reference_pattern' => 'That is not a valid regular expression.']);
        }
    }
}
