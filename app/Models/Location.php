<?php

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * FR-002-16: a branch of a claimed Business.
 *
 * @property array<string, mixed> $address
 * @property array<string, array{open: string, close: string}>|null $hours
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'slug', 'address', 'latitude', 'longitude', 'phone', 'hours'];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'hours' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public static function uniqueSlugFor(int $businessId, string $name, ?int $excludingId = null): string
    {
        $base = Str::slug($name) ?: 'location';
        $slug = $base;
        $suffix = 2;

        while (
            self::where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($excludingId, fn ($query) => $query->where('id', '!=', $excludingId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
