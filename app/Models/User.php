<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function providers(): HasMany
    {
        return $this->hasMany(UserProvider::class);
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
}
