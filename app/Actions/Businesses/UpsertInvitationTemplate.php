<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\DomainNormalizer;
use App\Domain\Invitations\GuardNeutralTemplate;
use App\Models\Business;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * FR-005-13, FR-005-14: the single place a template's text is ever
 * written, so `GuardNeutralTemplate` can't be bypassed by a different
 * write path. An unrecognised locale (edge case table) can't be checked
 * against the incentive lexicon, so it's neither silently allowed nor
 * silently rejected — it's saved inactive and logged for staff review
 * before first use, the same real-signal-now/full-006-console-later shape
 * as every other unbuilt staff-facing tool in this codebase.
 */
class UpsertInvitationTemplate
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Business $business, User $actor, string $locale, string $subject, string $body, ?string $senderName = null, ?string $replyTo = null): InvitationTemplate
    {
        if (! $business->userCan($actor, BusinessPermission::SendInvitations)) {
            throw new AuthorizationException('You cannot manage invitation templates for this business.');
        }

        $errors = GuardNeutralTemplate::check($subject, $body, $locale, $this->allowedDomains($business));

        if ($errors !== []) {
            throw ValidationException::withMessages(['body' => $errors]);
        }

        $isKnownLocale = in_array($locale, GuardNeutralTemplate::KNOWN_LOCALES, true);

        if (! $isKnownLocale) {
            Log::warning('Invitation template in an unrecognised locale is held for staff review', [
                'business_id' => $business->id,
                'locale' => $locale,
            ]);
        }

        return InvitationTemplate::updateOrCreate(
            ['business_id' => $business->id, 'locale' => $locale],
            [
                'subject' => $subject,
                'body' => $body,
                'sender_name' => $senderName,
                'reply_to' => $replyTo,
                'is_active' => $isKnownLocale,
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function allowedDomains(Business $business): array
    {
        $domains = array_merge(
            [$business->primary_domain],
            $business->additional_domains ?? [],
            [$business->website === null ? null : DomainNormalizer::normalize($business->website)],
        );

        return array_values(array_unique(array_filter($domains)));
    }
}
