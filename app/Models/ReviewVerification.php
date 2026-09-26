<?php

namespace App\Models;

use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use Database\Factories\ReviewVerificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * FR-004-01 through FR-004-11: one verification attempt for one Review.
 *
 * @property VerificationMethod $method
 * @property VerificationStatus $status
 * @property array<string, mixed>|null $extracted_fields
 * @property array<int, string>|null $proof_paths
 * @property Carbon|null $decided_at
 * @property Carbon|null $proof_deleted_at
 */
class ReviewVerification extends Model
{
    /** @use HasFactory<ReviewVerificationFactory> */
    use HasFactory;

    /**
     * Mirrors the DB-level default so a freshly-created instance already
     * reflects it without a round-trip.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = [
        'review_id',
        'method',
        'status',
        'business_verification_request_id',
        'reference_number',
        'extracted_fields',
        'confidence',
        'proof_fingerprint',
        'proof_paths',
        'decided_by',
        'decided_at',
        'decision_reason_code',
        'proof_deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => VerificationMethod::class,
            'status' => VerificationStatus::class,
            'extracted_fields' => 'array',
            'proof_paths' => 'array',
            'decided_at' => 'datetime',
            'proof_deleted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasOne<VerificationAttestation, $this>
     */
    public function attestation(): HasOne
    {
        return $this->hasOne(VerificationAttestation::class, 'verification_id');
    }
}
