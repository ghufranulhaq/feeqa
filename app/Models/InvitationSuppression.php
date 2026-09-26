<?php

namespace App\Models;

use Database\Factories\InvitationSuppressionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-005-15, FR-005-17: `business_id` null means "from any business"
 * (platform-wide). Permanent until the recipient reverses it — there is
 * no expiry column by design.
 */
class InvitationSuppression extends Model
{
    /** @use HasFactory<InvitationSuppressionFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'recipient_email_hash',
        'reason',
    ];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * FR-005-09, FR-005-15: a business-level suppression blocks that
     * Business only; a `business_id` null row (platform-wide unsubscribe,
     * or FR-005-17's own hard-bounce/complaint rows) blocks every Business.
     */
    public static function suppresses(int $businessId, string $recipientEmailHash): bool
    {
        return self::where('recipient_email_hash', $recipientEmailHash)
            ->where(fn ($query) => $query->whereNull('business_id')->orWhere('business_id', $businessId))
            ->exists();
    }
}
