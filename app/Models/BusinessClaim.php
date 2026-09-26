<?php

namespace App\Models;

use App\Domain\Businesses\ClaimMethod;
use App\Domain\Businesses\ClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-002-11 through FR-002-15.
 *
 * @property array<int, string>|null $documents
 * @property ClaimMethod $method
 * @property ClaimStatus $status
 * @property Carbon|null $code_expires_at
 * @property Carbon|null $owner_response_deadline
 * @property Carbon|null $reviewed_at
 */
class BusinessClaim extends Model
{
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
        'business_id', 'user_id', 'method', 'status', 'target', 'verification_code',
        'attempts', 'code_expires_at', 'verification_token', 'owner_response_deadline',
        'documents', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'method' => ClaimMethod::class,
            'status' => ClaimStatus::class,
            'documents' => 'array',
            'code_expires_at' => 'datetime',
            'owner_response_deadline' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
