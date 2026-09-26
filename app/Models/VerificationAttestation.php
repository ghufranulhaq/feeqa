<?php

namespace App\Models;

use App\Domain\Verification\VerificationMethod;
use Database\Factories\VerificationAttestationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-004-14 through FR-004-17: a signed, publicly-checkable attestation
 * behind a Verified Experience badge. UUID-keyed (see the migration) so
 * the public check page never leaks whether a given ID was ever issued.
 *
 * @property VerificationMethod $method
 * @property Carbon $experience_month
 * @property Carbon $decision_time
 * @property Carbon|null $revoked_at
 */
class VerificationAttestation extends Model
{
    /** @use HasFactory<VerificationAttestationFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'review_id',
        'business_id',
        'verification_id',
        'method',
        'experience_month',
        'decision_time',
        'methodology_version',
        'jws',
        'revoked_at',
        'revoked_by',
        'revoked_reason_code',
    ];

    protected function casts(): array
    {
        return [
            'method' => VerificationMethod::class,
            'experience_month' => 'date',
            'decision_time' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<ReviewVerification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(ReviewVerification::class, 'verification_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
