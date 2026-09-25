<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $requested_at
 * @property Carbon|null $ready_at
 * @property Carbon|null $expires_at
 */
class DataExport extends Model
{
    protected $fillable = ['user_id', 'status', 'file_path', 'requested_at', 'ready_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'ready_at' => 'datetime',
            'expires_at' => 'datetime',
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
     * Edge case: "Export requested twice within 24 hours → return the
     * pending export."
     */
    public function scopeRequestedRecently(Builder $query): Builder
    {
        return $query->where('requested_at', '>', Carbon::now()->subDay());
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready'
            && $this->file_path !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
