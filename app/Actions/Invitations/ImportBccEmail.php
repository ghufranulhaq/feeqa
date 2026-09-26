<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Invitations\BccImportOutcome;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\ParsedEmail;
use App\Domain\Invitations\RawEmailParser;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * FR-005-01 (bcc), FR-005-02, FR-005-06, FR-005-07: a demo-only `.eml`
 * upload standing in for real inbound mail (plan D14 — there is no SMTP/
 * SPF/DKIM infrastructure to receive one for real). The Business is
 * already known from the route, the same simplification the real
 * infrastructure would resolve from which unique BCC address the message
 * was addressed to. Every branch discards the email body immediately after
 * the fields below are pulled out of it — nothing keeps the raw message
 * past this method returning.
 */
class ImportBccEmail
{
    private const MAX_FILE_SIZE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly CreateInvitation $createInvitation,
        private readonly MalwareScanner $scanner,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Business $business, User $actor, UploadedFile $file): ImportBccEmailResult
    {
        if (! $business->userCan($actor, BusinessPermission::ManageIntegrations)) {
            throw new AuthorizationException('You cannot import BCC email for this business.');
        }

        $this->guardFile($file);

        $raw = file_get_contents($file->getRealPath());

        if ($raw === false || trim($raw) === '') {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        $parsed = RawEmailParser::parse($raw);
        unset($raw);

        if ($parsed->recipientEmail === null) {
            throw ValidationException::withMessages(['file' => 'The email has no To: recipient to invite.']);
        }

        if (! $this->isAligned($business, $parsed)) {
            $business->increment('bcc_failed_alignment_count');

            return new ImportBccEmailResult(BccImportOutcome::FailedAlignment);
        }

        $reference = $this->extractReference($business, $parsed->searchableText);

        if ($reference === null) {
            // FR-005-07 edge case: "BCC email that isn't a transactional
            // email (e.g., newsletter) — the reference pattern doesn't
            // match, so no invitation is created and it is counted in
            // diagnostics."
            $business->increment('bcc_no_reference_match_count');

            return new ImportBccEmailResult(BccImportOutcome::NoReferenceMatch);
        }

        $invitation = $this->createInvitation->handle($business, InvitationMethod::Bcc, [
            'recipient_email' => $parsed->recipientEmail,
            'recipient_name' => $parsed->recipientName,
            'reference' => $reference,
        ], createdBy: $actor);

        return new ImportBccEmailResult(BccImportOutcome::Imported, $invitation);
    }

    /**
     * FR-005-06: alignment succeeds against either identity independently
     * — a verified domain match, or an exact registered sender address —
     * but both SPF and DKIM must have already passed for either to count.
     */
    private function isAligned(Business $business, ParsedEmail $parsed): bool
    {
        if (! $parsed->spfPass || ! $parsed->dkimPass) {
            return false;
        }

        $verifiedDomains = array_filter(array_merge(
            [$business->primary_domain],
            $business->additional_domains ?? [],
        ));

        if ($parsed->dkimDomain !== null && in_array($parsed->dkimDomain, $verifiedDomains, true)) {
            return true;
        }

        if ($parsed->spfMailFromDomain !== null && in_array($parsed->spfMailFromDomain, $verifiedDomains, true)) {
            return true;
        }

        $registeredSenders = array_map('strtolower', $business->bcc_registered_senders ?? []);

        return $parsed->fromAddress !== null && in_array(strtolower($parsed->fromAddress), $registeredSenders, true);
    }

    private function extractReference(Business $business, string $searchableText): ?string
    {
        $pattern = $business->bcc_reference_pattern ?? config('platform.invitations.bcc.default_reference_pattern');

        return preg_match($pattern, $searchableText, $matches) === 1 ? $matches[0] : null;
    }

    private function guardFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages(['file' => 'The file must be 5 MB or smaller.']);
        }

        $scan = $this->scanner->scan($file->getRealPath());

        if (! $scan->clean) {
            throw ValidationException::withMessages(['file' => 'The file failed a malware scan.']);
        }
    }
}
