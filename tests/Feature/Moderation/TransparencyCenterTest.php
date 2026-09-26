<?php

use App\Actions\Moderation\ListMethodologyLinks;
use App\Actions\Moderation\ListTransparencyReports;
use App\Models\Business;
use App\Models\TransparencyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists generated transparency reports newest first (FR-006-21e)', function () {
    $older = TransparencyReport::factory()->create(['period_start' => '2026-01-01', 'period_end' => '2026-03-31']);
    $newer = TransparencyReport::factory()->create(['period_start' => '2026-04-01', 'period_end' => '2026-06-30']);

    $reports = (new ListTransparencyReports)->handle();

    expect($reports->pluck('id')->all())->toBe([$newer->id, $older->id]);
});

it('documents the score/verification methodology links honestly (FR-006-21c, FR-006-21d)', function () {
    $links = (new ListMethodologyLinks)->handle();

    expect($links['review_score'])->toBeNull()
        ->and($links['trust_index'])->toBeNull()
        ->and($links['verification'])->toBeString();
});

it('lists only businesses currently under a Consumer Warning (FR-006-21f)', function () {
    $warned = Business::factory()->create(['consumer_warning_at' => now()]);
    Business::factory()->create(['consumer_warning_at' => null]);

    $result = Business::underConsumerWarning()->get();

    expect($result->pluck('id')->all())->toBe([$warned->id]);
});
