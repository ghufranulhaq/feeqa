<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-002-02: a permanent redirect from a Business's previous slug.
 */
class BusinessSlugRedirect extends Model
{
    protected $fillable = ['old_slug', 'business_id'];

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
