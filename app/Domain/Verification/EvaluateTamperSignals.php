<?php

namespace App\Domain\Verification;

/**
 * FR-004-07: "file metadata inconsistencies, editing-software signatures,
 * duplicate or near-duplicate image hashes across different accounts, and
 * templates known to be fake." Near-duplicate detection against other
 * accounts' proofs is a database lookup the caller (T3) performs and
 * passes in as a plain bool, keeping this class a pure function of its
 * inputs, like App\Actions\Reviews\ScreenReviewSubmission's blocklist check.
 */
final class EvaluateTamperSignals
{
    public function handle(
        ?string $exifSoftwareTag,
        bool $hasNearDuplicateAcrossOtherAccounts,
        ?string $perceptualHash,
    ): TamperSignalResult {
        if ($exifSoftwareTag !== null && $this->matchesEditingTool($exifSoftwareTag)) {
            return TamperSignalResult::fail('editing_software_detected');
        }

        if ($hasNearDuplicateAcrossOtherAccounts) {
            return TamperSignalResult::fail('near_duplicate_image_other_account');
        }

        if ($perceptualHash !== null && $this->matchesKnownFakeTemplate($perceptualHash)) {
            return TamperSignalResult::fail('known_fake_template');
        }

        return TamperSignalResult::pass();
    }

    private function matchesEditingTool(string $exifSoftwareTag): bool
    {
        $haystack = mb_strtolower($exifSoftwareTag);

        /** @var list<string> $blocklist */
        $blocklist = config('platform.verification.editing_tool_blocklist', []);

        foreach ($blocklist as $tool) {
            $tool = mb_strtolower(trim($tool));

            if ($tool !== '' && str_contains($haystack, $tool)) {
                return true;
            }
        }

        return false;
    }

    private function matchesKnownFakeTemplate(string $perceptualHash): bool
    {
        /** @var list<string> $knownHashes */
        $knownHashes = config('platform.verification.known_fake_template_hashes', []);

        return in_array(mb_strtolower($perceptualHash), array_map('mb_strtolower', $knownHashes), true);
    }
}
