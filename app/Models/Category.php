<?php

namespace App\Models;

use App\Domain\Businesses\CategoryState;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A node in the category tree (FR-002-18), up to 3 levels deep
 * (`MAX_DEPTH`). A top-level node (`parent_id` null) is an "industry" and
 * additionally carries the FR-002-30 lifecycle in `state`; every other
 * node only has the plain FR-002-18/22 `launched` flag.
 *
 * @property array<string, string> $name
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    public const MAX_DEPTH = 3;

    /**
     * FR-002-29/FR-002-35: the fallback a consumer picks when nothing
     * else fits, seeded by CategoriesSeeder.
     */
    public const OTHER_UNCATEGORISED_SLUG = 'other-uncategorised';

    protected $fillable = [
        'parent_id',
        'slug',
        'name',
        'icon',
        'launched',
        'state',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'launched' => 'boolean',
            'is_system' => 'boolean',
            'state' => CategoryState::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function isIndustry(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * @return HasMany<CategoryQuestionSet, $this>
     */
    public function questionSets(): HasMany
    {
        return $this->hasMany(CategoryQuestionSet::class);
    }

    /**
     * FR-002-20: the highest version is the current one.
     */
    public function currentQuestionSet(): ?CategoryQuestionSet
    {
        return $this->questionSets()->orderByDesc('version')->first();
    }

    /**
     * FR-002-19: "Leaf categories inherit questions from their parents."
     * Walks root-to-leaf so a more specific category's question
     * overrides an ancestor's with the same key, in the ancestor's
     * original position, rather than duplicating it.
     *
     * @return Collection<int, CategoryQuestion>
     */
    public function effectiveQuestions(): Collection
    {
        $chain = [];
        $node = $this;

        while ($node !== null) {
            $chain[] = $node;
            $node = $node->parent;
        }

        $byKey = [];

        foreach (array_reverse($chain) as $category) {
            $questionSet = $category->currentQuestionSet();

            if ($questionSet === null) {
                continue;
            }

            foreach ($questionSet->questions()->orderBy('order')->get() as $question) {
                $byKey[$question->key] = $question;
            }
        }

        return collect(array_values($byKey));
    }

    /**
     * Depth of this node if it were placed under $parent (1 for a
     * top-level industry). Used before insert/move to enforce MAX_DEPTH.
     */
    public static function depthUnder(?Category $parent): int
    {
        $depth = 1;

        while ($parent !== null) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    /**
     * Falls back to config('app.locale'), then "en-GB" (the launch
     * locale, constitution §1), then whatever localisation exists.
     */
    public function localisedName(?string $locale = null): string
    {
        $names = $this->name;
        $locale ??= config('app.locale');

        return $names[$locale] ?? $names['en-GB'] ?? (string) reset($names);
    }
}
