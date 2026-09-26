<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\ClaimMethod;
use App\Domain\Businesses\DomainNormalizer;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use App\Notifications\BusinessClaimCodeNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-11: the four claim methods.
 */
class StartBusinessClaim
{
    public function __construct(private readonly MalwareScanner $scanner) {}

    /**
     * @param  array{target?: ?string, notes?: ?string, documents?: list<UploadedFile>}  $data
     */
    public function handle(Business $business, User $claimant, ClaimMethod $method, array $data = []): BusinessClaim
    {
        return match ($method) {
            ClaimMethod::Email => $this->startEmail($business, $claimant, $data['target'] ?? null),
            ClaimMethod::DnsTxt, ClaimMethod::HtmlFile => $this->startDomainProof($business, $claimant, $method),
            ClaimMethod::Manual => $this->startManual($business, $claimant, $data['notes'] ?? null, $data['documents'] ?? []),
        };
    }

    private function startEmail(Business $business, User $claimant, ?string $target): BusinessClaim
    {
        if ($business->primary_domain === null) {
            throw ValidationException::withMessages(['method' => 'This business has no domain to send a code to — use manual review instead.']);
        }

        // FR-002-12.
        if (in_array($business->primary_domain, config('platform.business_claiming.free_email_domains'), true)) {
            throw ValidationException::withMessages(['method' => 'A business on a free email domain cannot be claimed this way.']);
        }

        if ($target === null || ! str_contains($target, '@')) {
            throw ValidationException::withMessages(['target' => 'A valid email address is required.']);
        }

        $targetDomain = DomainNormalizer::normalize(substr($target, strrpos($target, '@') + 1));

        if ($targetDomain !== $business->primary_domain) {
            throw ValidationException::withMessages(['target' => "That address must be on {$business->primary_domain}."]);
        }

        $claim = BusinessClaim::create([
            'business_id' => $business->id,
            'user_id' => $claimant->id,
            'method' => ClaimMethod::Email,
            'target' => strtolower($target),
            'verification_code' => (string) random_int(100000, 999999),
            'code_expires_at' => now()->addMinutes(30),
        ]);

        Notification::route('mail', $claim->target)->notify(new BusinessClaimCodeNotification($claim));

        return $claim;
    }

    private function startDomainProof(Business $business, User $claimant, ClaimMethod $method): BusinessClaim
    {
        if ($business->primary_domain === null) {
            throw ValidationException::withMessages(['method' => 'This business has no domain to verify — use manual review instead.']);
        }

        return BusinessClaim::create([
            'business_id' => $business->id,
            'user_id' => $claimant->id,
            'method' => $method,
            'verification_token' => Str::random(32),
        ]);
    }

    /**
     * @param  list<UploadedFile>  $documents
     */
    private function startManual(Business $business, User $claimant, ?string $notes, array $documents): BusinessClaim
    {
        $paths = [];

        foreach ($documents as $file) {
            $scan = $this->scanner->scan($file->getRealPath());

            if (! $scan->clean) {
                throw ValidationException::withMessages(['documents' => 'One of the files failed a malware scan.']);
            }

            // Private disk (constitution §5.6/P6: proof documents are
            // private by default).
            $path = "business-claims/{$business->id}/".Str::random(20).'.'.($file->getClientOriginalExtension() ?: 'bin');
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
            $paths[] = $path;
        }

        return BusinessClaim::create([
            'business_id' => $business->id,
            'user_id' => $claimant->id,
            'method' => ClaimMethod::Manual,
            'notes' => $notes,
            'documents' => $paths,
        ]);
    }
}
