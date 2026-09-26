<?php

namespace App\Models;

use App\Domain\Reviews\LifecycleMilestone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-003-19: records that the milestone reminder for a (review, milestone)
 * pair has already been sent, so re-running the daily command never
 * double-sends. Not itself the reminder — just a sent marker.
 *
 * @property LifecycleMilestone $milestone
 * @property Carbon $sent_at
 */
class ReviewLifecycleReminder extends Model
{
    protected $fillable = ['review_id', 'milestone', 'sent_at'];

    protected function casts(): array
    {
        return [
            'milestone' => LifecycleMilestone::class,
            'sent_at' => 'datetime',
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
