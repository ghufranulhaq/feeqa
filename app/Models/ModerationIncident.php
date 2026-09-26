<?php

namespace App\Models;

use App\Domain\Moderation\IncidentStatus;
use App\Domain\Moderation\IncidentType;
use Database\Factories\ModerationIncidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-006-06: one detected anomaly on a Business — a review spike, a
 * sudden rating shift, or a cluster of new accounts reviewing it at once.
 *
 * @property IncidentType $type
 * @property IncidentStatus $status
 * @property array<string, mixed> $metrics
 * @property Carbon|null $frozen_until
 */
class ModerationIncident extends Model
{
    /** @use HasFactory<ModerationIncidentFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'type',
        'status',
        'detected_at',
        'metrics',
        'frozen_until',
        'assigned_to',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'detected_at' => 'datetime',
            'metrics' => 'array',
            'frozen_until' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isFrozen(): bool
    {
        return $this->frozen_until !== null && $this->frozen_until->isFuture();
    }
}
