<?php

namespace App\Actions\Invitations;

use App\Models\Business;
use App\Models\InvitationTemplate;

/**
 * FR-005-13: a Business's own active template for the invitation's locale,
 * or the platform default when none is configured — sending an invitation
 * never blocks on `UpsertInvitationTemplate` (T2) having been used yet.
 */
class ResolveInvitationTemplate
{
    /**
     * @return array{subject: string, body: string, sender_name: ?string, reply_to: ?string}
     */
    public function handle(Business $business, string $locale): array
    {
        $template = InvitationTemplate::where('business_id', $business->id)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();

        if ($template !== null) {
            return [
                'subject' => $template->subject,
                'body' => $template->body,
                'sender_name' => $template->sender_name,
                'reply_to' => $template->reply_to,
            ];
        }

        return [...config('platform.invitations.default_template'), 'sender_name' => null, 'reply_to' => null];
    }
}
