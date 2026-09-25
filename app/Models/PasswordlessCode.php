<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * FR-001-01: passwordless email sign-in/sign-up. One row per request; the
 * 6-digit code and the magic-link token both consume the same row, so
 * using either one invalidates both.
 */
class PasswordlessCode extends Model
{
    protected $fillable = ['email', 'code', 'token', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function issueFor(string $email): self
    {
        return self::create([
            'email' => $email,
            'code' => (string) random_int(100000, 999999),
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('consumed_at')->where('expires_at', '>', Carbon::now());
    }

    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }
}
