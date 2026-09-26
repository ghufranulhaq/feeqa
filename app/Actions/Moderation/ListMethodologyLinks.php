<?php

namespace App\Actions\Moderation;

/**
 * FR-006-21(c), FR-006-21(d): the Transparency Center's links out to how
 * scores and verification work. `review_score`/`trust_index` are `null`
 * — 008 and 009 don't exist yet, a documented placeholder rather than a
 * guessed-at URL. `verification` isn't a dedicated page either (004's own
 * tasks.md T-whatever already noted this): its methodology is a line per
 * method on the public attestation check page itself, so this points
 * there instead of inventing a page 004 never built.
 */
class ListMethodologyLinks
{
    /**
     * @return array{review_score: null, trust_index: null, verification: string}
     */
    public function handle(): array
    {
        return [
            'review_score' => null,
            'trust_index' => null,
            'verification' => 'Described on each verification\'s own public check page (004): verification-check/{attestation}.',
        ];
    }
}
