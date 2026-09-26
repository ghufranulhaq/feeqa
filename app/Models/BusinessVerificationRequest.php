<?php

namespace App\Models;

use App\Domain\Verification\ConsumerVerificationResponse;
use App\Domain\Verification\VerificationRequestStatus;
use Database\Factories\BusinessVerificationRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-004-18 through FR-004-21: a business asking the reviewer to prove an
 * unverified review is genuine. At most one per review (unique review_id).
 *
 * @property VerificationRequestStatus $status
 * @property ConsumerVerificationResponse|null $consumer_response
 * @property Carbon $requested_at
 * @property Carbon|null $responded_at
 */
class BusinessVerificationRequest extends Model
{
    /** @use HasFactory<BusinessVerificationRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'review_id',
        'requested_by',
        'requested_at',
        'status',
        'responded_at',
        'consumer_response',
        'shared_reference_number',
    ];

    protected function casts(): array
    {
        return [
            'status' => VerificationRequestStatus::class,
            'consumer_response' => ConsumerVerificationResponse::class,
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
