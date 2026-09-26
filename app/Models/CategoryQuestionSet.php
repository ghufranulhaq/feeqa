<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * FR-002-20: one immutable version of a category's question set.
 *
 * @property Carbon $published_at
 */
class CategoryQuestionSet extends Model
{
    /**
     * FR-002-19: "0-8 attributes."
     */
    public const MAX_QUESTIONS = 8;

    protected $fillable = ['category_id', 'version', 'published_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<CategoryQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CategoryQuestion::class);
    }
}
