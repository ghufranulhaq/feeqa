<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-001-15, constitution §5.1. Append-only — nothing here is ever updated
 * or deleted; kept 6 years (§5.3).
 */
class ComplianceLogEntry extends Model
{
    protected $table = 'compliance_log';

    protected $fillable = ['staff_id', 'action', 'target_type', 'target_id', 'reason_code', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public static function record(User $staff, string $action, string $reasonCode, ?Model $target = null): self
    {
        return self::create([
            'staff_id' => $staff->id,
            'action' => $action,
            'target_type' => $target?->getMorphClass(),
            'target_id' => $target?->getKey(),
            'reason_code' => $reasonCode,
            'occurred_at' => now(),
        ]);
    }
}
