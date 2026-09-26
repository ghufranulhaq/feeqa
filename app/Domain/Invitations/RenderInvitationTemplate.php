<?php

namespace App\Domain\Invitations;

/**
 * FR-005-13: substitutes the two placeholders `GuardNeutralTemplate`
 * already guaranteed exist in every stored template before it renders an
 * email. Nothing else is templated — a business can't add its own
 * merge fields.
 */
final class RenderInvitationTemplate
{
    /**
     * @return array{subject: string, body: string}
     */
    public static function render(string $subject, string $body, string $reviewLink, string $unsubscribeLink): array
    {
        $replacements = [
            '{review_link}' => $reviewLink,
            '{unsubscribe_link}' => $unsubscribeLink,
        ];

        return [
            'subject' => strtr($subject, $replacements),
            'body' => strtr($body, $replacements),
        ];
    }
}
