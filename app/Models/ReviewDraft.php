<?php

namespace App\Models;

use Database\Factories\ReviewDraftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-003-10: an in-progress review, autosaved server-side for a signed-in
 * user so it can be restored on another device or after signing in
 * part-way through.
 *
 * @property array<string, mixed> $payload
 */
class ReviewDraft extends Model
{
    /** @use HasFactory<ReviewDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_id',
        'location_id',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
