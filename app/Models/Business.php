<?php

namespace App\Models;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessPlan;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\EmployeeSizeBand;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * FR-002-01: a business listing, claimed or not. Spec 001 T14 added a
 * minimal stand-in (id, name) just so business memberships/roles had
 * something to point at — this is spec 002's model, owning everything
 * else about a profile.
 *
 * The role/permission lookups below query model_has_roles directly rather
 * than going through Spatie's ambient "current team" state (plan D7's
 * SetPermissionTeam middleware sets that for the request's own actor, but
 * these methods are also used to check a *different* user — e.g. "is the
 * target already an Owner?" — where switching the global team context
 * mid-request would be fragile and easy to get wrong).
 *
 * @property BusinessStatus $status
 * @property BusinessPlan $plan
 * @property EmployeeSizeBand $employee_size_band
 * @property Carbon|null $claimed_at
 * @property Carbon|null $closed_at
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * Mirrors the DB-level defaults so a freshly-created instance already
     * reflects them without a round-trip (the DB defaults still apply to
     * any insert that bypasses Eloquent).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'unclaimed',
        'plan' => 'free',
        'employee_size_band' => 'unknown',
    ];

    protected $fillable = [
        'name',
        'slug',
        'primary_domain',
        'additional_domains',
        'bcc_address',
        'bcc_registered_senders',
        'bcc_reference_pattern',
        'country',
        'city',
        'status',
        'plan',
        'claimed_at',
        'closed_at',
        'description',
        'website',
        'email',
        'phone',
        'address',
        'social_links',
        'logo_path',
        'primary_category_id',
        'employee_size_band',
        'data_source',
        'import_batch',
    ];

    protected static function booted(): void
    {
        // Every business needs a unique slug (FR-002-01). Tests and
        // internal callers routinely create a Business with just a name
        // (e.g. spec 001's membership tests) — this keeps that working
        // without every caller having to compute one.
        static::creating(function (Business $business): void {
            $business->slug ??= self::uniqueSlugFor($business->name);
            $business->bcc_address ??= self::generateUniqueBccAddress();
            $business->review_link_token ??= self::generateUniqueReviewLinkToken();
        });
    }

    protected function casts(): array
    {
        return [
            'additional_domains' => 'array',
            'bcc_registered_senders' => 'array',
            'address' => 'array',
            'social_links' => 'array',
            'status' => BusinessStatus::class,
            'plan' => BusinessPlan::class,
            'employee_size_band' => EmployeeSizeBand::class,
            'claimed_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Edge cases table: "New reviews are blocked 12 months after the
     * closure date." Nothing calls this yet — review submission is spec
     * 003 — but the rule is defined here, in one place, ready for it.
     */
    public function acceptsNewReviews(): bool
    {
        if ($this->status !== BusinessStatus::Closed || $this->closed_at === null) {
            return true;
        }

        return $this->closed_at->diffInMonths(now()) < 12;
    }

    public static function uniqueSlugFor(string $name, ?int $excludingId = null): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 2;

        while (
            self::where('slug', $slug)
                ->when($excludingId, fn ($query) => $query->where('id', '!=', $excludingId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * FR-005-06: every Business gets a unique forwarding address without a
     * separate "activate BCC" step. `RotateBusinessBccAddress` is what
     * makes it rotatable after creation.
     */
    public static function generateUniqueBccAddress(): string
    {
        do {
            $address = 'biz-'.Str::lower(Str::random(16)).'@'.config('platform.invitations.bcc.domain');
        } while (self::where('bcc_address', $address)->exists());

        return $address;
    }

    public static function generateUniqueReviewLinkToken(): string
    {
        do {
            $token = Str::lower(Str::random(20));
        } while (self::where('review_link_token', $token)->exists());

        return $token;
    }

    /**
     * FR-005-04: the generic `link` method's own public, QR-encodable URL
     * — the same public profile page every other visitor reaches, tagged
     * so a future review-submission flow can tell a `link` visit apart
     * from an organic one and label the resulting review `Redirected`.
     */
    public function reviewLinkUrl(): string
    {
        return route('businesses.show', $this->slug).'?rl='.$this->review_link_token;
    }

    /**
     * FR-002-08 scenario 1: a consumer adding a business by domain alone
     * isn't asked for a name too — "skyhop-travel.com" becomes
     * "Skyhop Travel".
     */
    public static function guessNameFromDomain(string $domain): string
    {
        $withoutTld = str_contains($domain, '.') ? substr($domain, 0, strrpos($domain, '.')) : $domain;
        $words = trim(preg_replace('/[-_.]+/', ' ', $withoutTld) ?? '');

        return $words === '' ? $domain : Str::title($words);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    /**
     * FR-002-03: up to 5 secondary categories, alongside the primary one.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function secondaryCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'business_categories');
    }

    /**
     * FR-002-16.
     *
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * FR-002-27: "published reviews of other businesses that tag this
     * Business" — published reviews of another Business that tag this one
     * (FR-003-31), shown separately from the Business's own reviews and
     * never counted toward its scores (FR-003-32).
     *
     * @return Collection<int, Review>
     */
    public function mentions(): Collection
    {
        return Review::query()
            ->with(['business', 'reviewer'])
            ->publiclyVisible()
            ->where('tagged_business_id', $this->id)
            ->latest('published_at')
            ->get();
    }

    /**
     * FR-004-24: verified published customer reviews ÷ all published
     * customer reviews, rolling 12 months. A hook for specs 008/015 to
     * read later — same "built now, filled later" shape as
     * {@see RecalculateBusinessScore}. There is no
     * insider-review concept yet (spec 013 isn't built), so every publicly
     * visible review counts as a customer review today.
     */
    public function verificationPercentage(): float
    {
        $windowStart = now()->subMonths(12);

        $reviewIds = $this->reviews()
            ->publiclyVisible()
            ->where('published_at', '>=', $windowStart)
            ->pluck('id');

        if ($reviewIds->isEmpty()) {
            return 0.0;
        }

        $verifiedCount = VerificationAttestation::query()
            ->whereIn('review_id', $reviewIds)
            ->whereNull('revoked_at')
            ->distinct()
            ->count('review_id');

        return round($verifiedCount / $reviewIds->count() * 100, 1);
    }

    /**
     * @return list<string>
     */
    public function roleNamesFor(User $user): array
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.business_id', $this->id)
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->pluck('roles.name')
            ->all();
    }

    public function hasBusinessRole(User $user, BusinessRole $role): bool
    {
        return in_array($role->value, $this->roleNamesFor($user), true);
    }

    /**
     * FR-001-13: "A user must not be able to publish a customer review on a
     * Business where they hold a membership." Spec 003 (reviews) calls this
     * — nothing to guard yet since reviews don't exist.
     */
    public function hasMembership(User $user): bool
    {
        return $this->roleNamesFor($user) !== [];
    }

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array
    {
        $permissions = [];

        foreach ($this->roleNamesFor($user) as $roleName) {
            foreach (BusinessRole::from($roleName)->permissions() as $permission) {
                $permissions[$permission->value] = true;
            }
        }

        return array_keys($permissions);
    }

    public function userCan(User $user, BusinessPermission $permission): bool
    {
        return in_array($permission->value, $this->permissionsFor($user), true);
    }

    /**
     * FR-002-14: who to notify about a re-claim request.
     *
     * @return Collection<int, User>
     */
    public function owners(): Collection
    {
        $userIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.business_id', $this->id)
            ->where('roles.name', BusinessRole::Owner->value)
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * FR-003-32: "notification to the tagged Business's members" — every
     * user holding any role here, not just Owners (contrast owners()).
     *
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        $userIds = DB::table('model_has_roles')
            ->where('model_has_roles.business_id', $this->id)
            ->pluck('model_has_roles.model_id');

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * FR-001-11: "Every Business must have at least one Owner at all times."
     */
    public function ownerCount(): int
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.business_id', $this->id)
            ->where('roles.name', BusinessRole::Owner->value)
            ->count();
    }
}
