<?php

namespace App\Models;

use App\Domain\Businesses\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR-002-19: one context-aware prompt attribute.
 *
 * @property array<string, string> $label
 * @property array<string, list<string>>|null $options
 * @property QuestionType $type
 */
class CategoryQuestion extends Model
{
    protected $fillable = ['category_question_set_id', 'key', 'label', 'type', 'required', 'options', 'order'];

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'options' => 'array',
            'required' => 'boolean',
            'type' => QuestionType::class,
        ];
    }

    /**
     * @return BelongsTo<CategoryQuestionSet, $this>
     */
    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(CategoryQuestionSet::class, 'category_question_set_id');
    }

    public function localisedLabel(?string $locale = null): string
    {
        $labels = $this->label;
        $locale ??= config('app.locale');

        return $labels[$locale] ?? $labels['en-GB'] ?? (string) reset($labels);
    }
}
