<?php

namespace App\Models;

use Database\Factories\TransparencyReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FR-006-21(e), FR-006-22: one quarter's transparency figures, generated
 * by `ComputeTransparencyReport` straight from the compliance log, flags,
 * appeals, screenings, and enforcement actions — never hand-edited.
 *
 * @property array<string, mixed> $figures
 */
class TransparencyReport extends Model
{
    /** @use HasFactory<TransparencyReportFactory> */
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'figures',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'figures' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
