<?php

namespace App\Models;

use App\Domain\Moderation\GuidelineAudience;
use Database\Factories\GuidelineVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FR-006-01: one dated, immutable version of the Reviewer or Business
 * guidelines. Publishing a new version (T1's `PublishGuidelineVersion`)
 * never edits or deletes an old one — it only flips `is_current`.
 *
 * @property GuidelineAudience $audience
 */
class GuidelineVersion extends Model
{
    /** @use HasFactory<GuidelineVersionFactory> */
    use HasFactory;

    protected $fillable = [
        'audience',
        'version',
        'body',
        'published_at',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'audience' => GuidelineAudience::class,
            'published_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }
}
