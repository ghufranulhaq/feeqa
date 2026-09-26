<?php

use App\Actions\Staff\AuditScreeningSample;
use App\Domain\Reviews\ReviewStatus;
use App\Models\AuditSample;
use App\Models\Screening;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('samples at least 2% of the week\'s publish/reject screenings (FR-006-20)', function () {
    Screening::factory()->count(100)->create(['recommendation' => ReviewStatus::Published]);

    $sampled = (new AuditScreeningSample)->handle();

    expect($sampled->count())->toBeGreaterThanOrEqual(2)
        ->and(AuditSample::count())->toBe($sampled->count());
});

it('excludes held screenings from the sample', function () {
    Screening::factory()->count(10)->create(['recommendation' => ReviewStatus::Held]);

    $sampled = (new AuditScreeningSample)->handle();

    expect($sampled)->toBeEmpty();
});

it('excludes screenings outside the trailing week', function () {
    Screening::factory()->create(['recommendation' => ReviewStatus::Published, 'created_at' => now()->subWeeks(2)]);

    $sampled = (new AuditScreeningSample)->handle();

    expect($sampled)->toBeEmpty();
});

it('never samples the same screening twice', function () {
    $screening = Screening::factory()->create(['recommendation' => ReviewStatus::Rejected]);
    AuditSample::factory()->create(['screening_id' => $screening->id]);

    (new AuditScreeningSample)->handle();

    expect(AuditSample::where('screening_id', $screening->id)->count())->toBe(1);
});
