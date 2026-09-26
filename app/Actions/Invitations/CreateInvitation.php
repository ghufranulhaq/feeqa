<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\DefaultSendDelay;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\InvitationSuppression;
use App\Models\Location;
use App\Models\ReviewInvitation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FR-005-01, FR-005-05, FR-005-08 through FR-005-12: every invitation
 * method (manual, csv, api, bcc — link is a stable Business-level token,
 * never a per-recipient row) funnels through here, so the recipient/
 * reference/suppression rules can't be bypassed by a different write
 * path. Nothing this method sees is silently dropped (FR-005-20's own
 * principle, applied here too): a suppressed or business-member
 * recipient still gets a row, just an immediately-terminal one, so a CSV
 * import (T4) can report per-row outcomes instead of one row vanishing
 * with no trace.
 */
class CreateInvitation
{
    /**
     * @param  array{
     *     recipient_email: string, recipient_name?: ?string, locale?: string,
     *     reference?: ?string, product_skus?: ?array<int, string>,
     *     travel_date?: ?\DateTimeInterface, send_delay_days?: ?int,
     * }  $data
     */
    public function handle(Business $business, InvitationMethod $method, array $data, ?Location $location = null, ?User $createdBy = null): ReviewInvitation
    {
        $email = $data['recipient_email'];
        $reference = $data['reference'] ?? null;

        if ($reference !== null && ($existing = $this->existingByReference($business, $reference)) !== null) {
            // FR-005-09 "one per unique transaction reference", edge case
            // table's "API called twice with the same reference": always
            // idempotent, whichever method asks — not just the API one.
            return $existing;
        }

        $emailHash = TransactionRecordHash::email($email);

        if (($existing = $this->existingWithinRecipientWindow($business, $emailHash)) !== null) {
            // Edge case table: "Same customer with 3 orders in a week —
            // one invitation, the latest reference is attached."
            if ($reference !== null) {
                $existing->update(['reference' => $reference]);
            }

            return $existing->fresh();
        }

        $isBusinessMember = $business->members()->contains(
            fn (User $member) => strtolower($member->email) === strtolower($email)
        );

        if ($isBusinessMember) {
            Log::warning('Invitation recipient is a member of the business being invited — suppressed', [
                'business_id' => $business->id,
            ]);
        }

        $isSuppressed = $isBusinessMember || $this->isSuppressed($business, $emailHash);

        $days = $data['send_delay_days'] ?? DefaultSendDelay::days(
            $this->categorySlugs($business),
            isset($data['travel_date']),
        );
        $anchor = isset($data['travel_date']) ? Carbon::parse($data['travel_date']) : now();

        return ReviewInvitation::create([
            'business_id' => $business->id,
            'location_id' => $location?->id,
            'created_by' => $createdBy?->id,
            'method' => $method,
            'status' => $isSuppressed ? InvitationStatus::Suppressed : InvitationStatus::Queued,
            'recipient_email' => $email,
            'recipient_name' => $data['recipient_name'] ?? null,
            'locale' => $data['locale'] ?? 'en-GB',
            'reference' => $reference,
            'product_skus' => $data['product_skus'] ?? null,
            'token' => Str::random(48),
            'scheduled_at' => $anchor->copy()->addDays($days),
            'expires_at' => now()->addDays(60),
        ]);
    }

    private function existingByReference(Business $business, string $reference): ?ReviewInvitation
    {
        return ReviewInvitation::where('business_id', $business->id)
            ->where('reference_hash', TransactionRecordHash::reference($reference))
            ->first();
    }

    /**
     * FR-005-09: "never more than one invitation per recipient per
     * Business per 30 days."
     */
    private function existingWithinRecipientWindow(Business $business, string $emailHash): ?ReviewInvitation
    {
        return ReviewInvitation::where('business_id', $business->id)
            ->where('recipient_email_hash', $emailHash)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->first();
    }

    private function isSuppressed(Business $business, string $emailHash): bool
    {
        return InvitationSuppression::where('recipient_email_hash', $emailHash)
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $business->id))
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function categorySlugs(Business $business): array
    {
        $slugs = [];
        $category = $business->primaryCategory;

        while ($category !== null) {
            $slugs[] = $category->slug;
            $category = $category->parent;
        }

        return $slugs;
    }
}
