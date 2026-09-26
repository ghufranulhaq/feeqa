<?php

use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('computes a neutrality report for every business with at least one invitation (FR-005-18)', function () {
    Log::spy();
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(30)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->count(20)->cancelled()->create(['created_at' => now()]);
    Business::factory()->create();

    $this->artisan('review-invitations:neutrality-report')->assertExitCode(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'cancellation rate exceeds 20%'))
        ->once();
});
