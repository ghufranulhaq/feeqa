<?php

namespace App\Models;

use App\Domain\Businesses\BusinessPermission;
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
        'employee_size_band' => 'unknown',
    ];

    protected $fillable = [
        'name',
        'slug',
        'primary_domain',
        'additional_domains',
        'country',
        'city',
        'status',
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
        });
    }

    protected function casts(): array
    {
        return [
            'additional_domains' => 'array',
            'address' => 'array',
            'social_links' => 'array',
            'status' => BusinessStatus::class,
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
     * Business" — shown separately from the Business's own reviews and
     * never counted toward its scores. Always empty until spec 003 T12
     * wires it to a real Review query; the section itself was built in
     * spec 002 T13 (same "built now, filled later" pattern as spec 001
     * T5's reviewer profile).
     *
     * @return Collection<int, mixed>
     */
    public function mentions(): Collection
    {
        return collect();
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
