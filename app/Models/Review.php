<?php

namespace App\Models;

use App\Domain\Reviews\DurabilitySignal;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * FR-003-01, FR-003-02: a consumer's rating and account of one experience
 * with a Business (or one of its Locations).
 *
 * @property ReviewStatus $status
 * @property SourceLabel $source_label
 * @property array<string, mixed>|null $answers
 * @property Carbon $date_of_experience
 * @property Carbon|null $published_at
 * @property Carbon|null $edited_at
 * @property DurabilitySignal|null $durability_signal
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'location_id',
        'reviewer_id',
        'status',
        'source_label',
        'star_rating',
        'title',
        'text',
        'date_of_experience',
        'reference_number',
        'language',
        'question_set_version',
        'answers',
        'tagged_business_id',
        'confirmed_genuine',
        'idempotency_key',
        'published_at',
        'edited_at',
        'durability_signal',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'source_label' => SourceLabel::class,
            'answers' => 'array',
            'date_of_experience' => 'date',
            'confirmed_genuine' => 'boolean',
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
            'durability_signal' => DurabilitySignal::class,
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
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * FR-003-31: the other Business this review optionally names (e.g. the
     * agency that sold the ticket).
     *
     * @return BelongsTo<Business, $this>
     */
    public function taggedBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'tagged_business_id');
    }

    /**
     * @return HasMany<ReviewLifecycleUpdate, $this>
     */
    public function lifecycleUpdates(): HasMany
    {
        return $this->hasMany(ReviewLifecycleUpdate::class);
    }

    /**
     * @return HasMany<ReviewUsefulVote, $this>
     */
    public function usefulVotes(): HasMany
    {
        return $this->hasMany(ReviewUsefulVote::class);
    }

    /**
     * @return HasMany<ReviewVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(ReviewVerification::class);
    }

    /**
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Published);
    }

    /**
     * FR-003-26: what a public review list may show — published, and not
     * by an author whose account deletion is pending (FR-001-20, "public
     * content is hidden right away").
     *
     * @param  Builder<Review>  $query
     * @return Builder<Review>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->published()->whereHas('reviewer', fn (Builder $reviewer) => $reviewer->whereNull('deletion_requested_at'));
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === ReviewStatus::Published && ! $this->reviewer->hasPendingDeletion();
    }

    /**
     * FR-003-21: "the latest published update's rating, or the original
     * rating if there are no updates."
     */
    public function currentRating(): int
    {
        $latestPublished = $this->lifecycleUpdates()
            ->where('status', ReviewStatus::Published)
            ->latest('published_at')
            ->first();

        if ($latestPublished === null) {
            return $this->star_rating;
        }

        return $latestPublished->star_rating;
    }
}
