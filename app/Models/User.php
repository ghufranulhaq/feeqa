<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Staff\StaffRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $date_of_birth_confirmed_at
 * @property Carbon|null $deletion_requested_at
 * @property-read string|null $avatar
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'locale',
        'avatar_path',
        'date_of_birth_confirmed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['avatar'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth_confirmed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The public URL of the uploaded avatar, if any — what the existing
     * shadcn Avatar components (`user.avatar`) already expect.
     */
    protected function avatar(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<UserProvider, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(UserProvider::class);
    }

    /**
     * @return HasMany<DataExport, $this>
     */
    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * FR-001-21: "must ask again for consent when the terms change in a
     * material way" — true when there's no consent on record, or the most
     * recent one no longer matches the published terms/privacy versions.
     */
    public function needsReconsent(): bool
    {
        /** @var Consent|null $latest */
        $latest = $this->consents()->latest('consented_at')->first();

        return $latest === null || ! $latest->isCurrent();
    }

    /**
     * FR-001-13: "One person may hold both a consumer identity and
     * business memberships under the same login" — a staff role is the
     * same idea, on the same User row.
     */
    public function staffRole(): ?StaffRole
    {
        return $this->staff_role ? StaffRole::from($this->staff_role) : null;
    }

    public function isStaff(): bool
    {
        return $this->staff_role !== null;
    }

    /**
     * FR-001-20.
     */
    public function hasPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }
}
