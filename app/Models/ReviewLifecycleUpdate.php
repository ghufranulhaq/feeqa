<?php

namespace App\Models;

use App\Domain\Reviews\LifecycleMilestone;
use App\Domain\Reviews\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-003-17 to FR-003-22: one dated follow-up on a published review.
 *
 * @property LifecycleMilestone $milestone
 * @property ReviewStatus $status
 * @property array<string, mixed>|null $answers
 * @property Carbon|null $published_at
 * @property Carbon|null $edited_at
 */
class ReviewLifecycleUpdate extends Model
{
    protected $fillable = [
        'review_id',
        'milestone',
        'status',
        'star_rating',
        'text',
        'answers',
        'published_at',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'milestone' => LifecycleMilestone::class,
            'status' => ReviewStatus::class,
            'answers' => 'array',
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
