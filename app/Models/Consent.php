<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-001-21. One row per consent event — never updated in place.
 *
 * @property string $terms_version
 * @property string $privacy_version
 * @property bool $marketing_opt_in
 * @property Carbon $consented_at
 */
class Consent extends Model
{
    protected $fillable = [
        'user_id',
        'terms_version',
        'privacy_version',
        'marketing_opt_in',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
            'consented_at' => 'datetime',
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
     * Whether this consent still matches the currently published terms and
     * privacy versions (config/legal.php). False means the user needs to
     * consent again.
     */
    public function isCurrent(): bool
    {
        return $this->terms_version === config('legal.terms_version')
            && $this->privacy_version === config('legal.privacy_version');
    }
}
