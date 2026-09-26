<?php

namespace App\Models;

use Database\Factories\ModeratorConflictFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Edge cases table: a staff member declaring themselves conflicted on a
 * Business — `ModerateReview` and `DecideFlag` both refuse to act on that
 * Business's content for that staff member.
 */
class ModeratorConflict extends Model
{
    /** @use HasFactory<ModeratorConflictFactory> */
    use HasFactory;

    protected $fillable = ['staff_id', 'business_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
