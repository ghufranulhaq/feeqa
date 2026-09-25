<?php

namespace App\Models;

use App\Domain\Businesses\BusinessRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
class BusinessInvitation extends Model
{
    protected $fillable = ['business_id', 'email', 'role', 'token', 'invited_by', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function businessRole(): BusinessRole
    {
        return BusinessRole::from($this->role);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', Carbon::now());
    }

    public static function issue(Business $business, User $inviter, string $email, BusinessRole $role): self
    {
        return self::create([
            'business_id' => $business->id,
            'email' => $email,
            'role' => $role->value,
            'token' => Str::random(64),
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
