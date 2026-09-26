<?php

namespace Database\Factories;

use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReviewInvitation>
 */
class ReviewInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();

        return [
            'business_id' => Business::factory(),
            'method' => InvitationMethod::Manual,
            'status' => InvitationStatus::Queued,
            'recipient_email' => $email,
            'recipient_name' => $this->faker->name(),
            'locale' => 'en-GB',
            'token' => Str::random(48),
            'scheduled_at' => now()->addDays(7),
            'expires_at' => now()->addDays(67),
        ];
    }

    public function withReference(?string $reference = null): static
    {
        $reference ??= strtoupper($this->faker->bothify('SK-######'));

        return $this->state(fn () => ['reference' => $reference]);
    }

    public function bcc(): static
    {
        return $this->state(fn () => ['method' => InvitationMethod::Bcc]);
    }

    public function api(): static
    {
        return $this->state(fn () => ['method' => InvitationMethod::Api]);
    }

    public function csv(): static
    {
        return $this->state(fn () => ['method' => InvitationMethod::Csv]);
    }

    public function link(): static
    {
        return $this->state(fn () => [
            'method' => InvitationMethod::Link,
            'recipient_email' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => InvitationStatus::Sent, 'sent_at' => now()]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => InvitationStatus::Reviewed, 'reviewed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => InvitationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'order_cancelled',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
