<?php

namespace App\Models;

use App\Domain\Businesses\EmployeeSizeBand;
use App\Domain\Businesses\EmployeeSizeBandDisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * FR-002-25, edge cases table.
 *
 * @property EmployeeSizeBandDisputeStatus $status
 * @property EmployeeSizeBand|null $proposed_band
 * @property Carbon|null $decided_at
 */
class EmployeeSizeBandDispute extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected $fillable = ['business_id', 'submitted_by', 'evidence', 'proposed_band'];

    protected function casts(): array
    {
        return [
            'status' => EmployeeSizeBandDisputeStatus::class,
            'proposed_band' => EmployeeSizeBand::class,
            'decided_at' => 'datetime',
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
