<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Invitations\InvitationMethod;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * FR-005-01 (api): the "Invitation API" data shape this FR asks for. The
 * actual external, token-authenticated Invitation API contract is spec
 * 016's job — same relationship 004's `SubmitTransactionRecords` has with
 * its own Business API — this is the real path underneath it, reachable
 * today from the business dashboard. Reference-based idempotency (edge
 * case: "API called twice with the same reference") needs no logic here;
 * it's T3's `CreateInvitation` own reference lookup, run for every method.
 */
class RequestApiInvitation
{
    public function __construct(private readonly CreateInvitation $createInvitation) {}

    /**
     * @param  array{
     *     recipient_email: string, recipient_name?: ?string, locale?: string,
     *     reference: string, product_skus?: ?array<int, string>,
     *     travel_date?: ?\DateTimeInterface, send_at?: ?\DateTimeInterface,
     * }  $data
     *
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, array $data): ReviewInvitation
    {
        if (! $business->userCan($actor, BusinessPermission::ManageIntegrations)) {
            throw new AuthorizationException('You cannot call the invitation API for this business.');
        }

        if (isset($data['send_at'])) {
            // Edge case: "API called with a past send time — send at the
            // next allowed send window." No per-Business send-window
            // setting exists yet (FR-005-08's other half), so the next
            // allowed moment is simply now — the same honest-placeholder
            // relationship this spec already has with unbuilt settings.
            $sendAt = Carbon::parse($data['send_at']);
            $data['scheduled_at'] = $sendAt->isPast() ? now() : $sendAt;
            unset($data['send_at']);
        }

        return $this->createInvitation->handle($business, InvitationMethod::Api, $data, createdBy: $actor);
    }
}
