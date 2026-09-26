<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\DomainNormalizer;
use App\Models\Business;
use App\Models\BusinessProfileChangeRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-002-03, FR-002-07: everything but name/domain/primary category
 * publishes right away; those three queue on a claimed profile instead
 * (App\Models\BusinessProfileChangeRequest) and are reviewed by staff
 * (App\Actions\Staff\ReviewBusinessProfileChangeRequest). An unclaimed
 * profile has no Owner/Admin to submit them in practice today, but FR-002-07
 * only gates a *claimed* profile, so they'd still apply immediately there.
 */
class UpdateBusinessProfile
{
    private const IMMEDIATE_FIELDS = ['description', 'website', 'email', 'phone', 'address', 'social_links'];

    /**
     * @param array{
     *     description?: ?string, website?: ?string, email?: ?string, phone?: ?string,
     *     address?: ?array<string, mixed>, social_links?: ?array<string, mixed>,
     *     secondary_category_ids?: ?array<int, int>,
     *     name?: ?string, domain?: ?string, category_id?: ?int,
     * } $data
     *
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, array $data): ProfileUpdateResult
    {
        if (! $business->userCan($actor, BusinessPermission::EditProfile)) {
            throw new AuthorizationException('You cannot edit this business profile.');
        }

        $immediate = array_intersect_key($data, array_flip(self::IMMEDIATE_FIELDS));

        if ($immediate !== []) {
            $business->update($immediate);
        }

        if (array_key_exists('secondary_category_ids', $data)) {
            $business->secondaryCategories()->sync($data['secondary_category_ids'] ?? []);
        }

        $changeRequest = $this->applyOrQueueSensitiveChanges($business, $actor, $data);

        return new ProfileUpdateResult($business->fresh(), $changeRequest);
    }

    private function applyOrQueueSensitiveChanges(Business $business, User $actor, array $data): ?BusinessProfileChangeRequest
    {
        $changes = $this->sensitiveChanges($business, $data);

        if ($changes === []) {
            return null;
        }

        if ($business->status !== BusinessStatus::Claimed) {
            $business->update(array_map(fn (array $change) => $change['new'], $changes));

            return null;
        }

        return BusinessProfileChangeRequest::create([
            'business_id' => $business->id,
            'requested_by' => $actor->id,
            'changes' => $changes,
        ]);
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function sensitiveChanges(Business $business, array $data): array
    {
        $changes = [];

        if (! empty($data['name']) && $data['name'] !== $business->name) {
            $changes['name'] = ['old' => $business->name, 'new' => $data['name']];
        }

        if (! empty($data['domain'])) {
            $normalized = DomainNormalizer::normalize($data['domain']);

            if ($normalized !== null && $normalized !== $business->primary_domain) {
                $changes['primary_domain'] = ['old' => $business->primary_domain, 'new' => $normalized];
            }
        }

        if (! empty($data['category_id']) && $data['category_id'] !== $business->primary_category_id) {
            $changes['primary_category_id'] = ['old' => $business->primary_category_id, 'new' => $data['category_id']];
        }

        return $changes;
    }
}
