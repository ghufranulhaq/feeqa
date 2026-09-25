<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $provider
 * @property string $provider_user_id
 * @property Carbon $created_at
 */
class UserProvider extends Model
{
    protected $fillable = ['user_id', 'provider', 'provider_user_id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
