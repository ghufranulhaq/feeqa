<?php

use App\Actions\Invitations\ComputeInvitationAnalytics;
use App\Domain\Invitations\InvitationMethod;
use App\Models\Business;
use App\Models\InvitationTemplate;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds the funnel from each lifecycle timestamp (FR-005-19)', function () {
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->create();
    ReviewInvitation::factory()->for($business)->create(['sent_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['sent_at' => now(), 'delivered_at' => now(), 'opened_at' => now()]);
    ReviewInvitation::factory()->for($business)->reviewed()->create(['sent_at' => now(), 'delivered_at' => now(), 'opened_at' => now(), 'clicked_at' => now()]);

    $analytics = (new ComputeInvitationAnalytics)->handle($business);

    expect($analytics['funnel'])->toBe([
        'queued' => 4,
        'sent' => 3,
        'delivered' => 2,
        'opened' => 2,
        'clicked' => 1,
        'reviewed' => 1,
    ]);
});

it('computes conversion as reviewed over sent, and null with nothing sent yet', function () {
    $business = Business::factory()->create();

    expect((new ComputeInvitationAnalytics)->handle($business)['conversion'])->toBeNull();

    ReviewInvitation::factory()->for($business)->count(3)->create(['sent_at' => now()]);
    ReviewInvitation::factory()->for($business)->reviewed()->create(['sent_at' => now()]);

    $analytics = (new ComputeInvitationAnalytics)->handle($business);

    expect($analytics['conversion'])->toBe(0.25);
});

it('breaks totals and reviewed counts down by method and by template', function () {
    $business = Business::factory()->create();
    $template = InvitationTemplate::factory()->for($business)->create();
    ReviewInvitation::factory()->for($business)->create(['method' => InvitationMethod::Manual]);
    ReviewInvitation::factory()->for($business)->reviewed()->create(['method' => InvitationMethod::Manual]);
    ReviewInvitation::factory()->for($business)->create(['method' => InvitationMethod::Csv, 'template_id' => $template->id]);

    $analytics = (new ComputeInvitationAnalytics)->handle($business);

    expect($analytics['by_method'])->toBe([
        'manual' => ['total' => 2, 'reviewed' => 1],
        'csv' => ['total' => 1, 'reviewed' => 0],
    ])->and($analytics['by_template'])->toBe([
        $template->id => ['total' => 1, 'reviewed' => 0],
    ]);
});

it('groups invitations created in the last 30 days by day for the over-time series', function () {
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(2)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['created_at' => now()->subDays(40)]);

    $analytics = (new ComputeInvitationAnalytics)->handle($business);

    expect($analytics['over_time'])->toBe([now()->toDateString() => 2]);
});
