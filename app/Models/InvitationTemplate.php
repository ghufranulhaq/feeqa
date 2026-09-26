<?php

namespace App\Models;

use Database\Factories\InvitationTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-005-13, FR-005-14: one editable template per Business per locale.
 * `body` is only ever written after passing
 * `App\Domain\Invitations\GuardNeutralTemplate` — see
 * `App\Actions\Businesses\UpsertInvitationTemplate`.
 */
class InvitationTemplate extends Model
{
    /** @use HasFactory<InvitationTemplateFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected $fillable = [
        'business_id',
        'locale',
        'subject',
        'body',
        'sender_name',
        'reply_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
