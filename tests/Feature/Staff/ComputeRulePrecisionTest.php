<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Staff\ComputeRulePrecision;
use App\Domain\Reviews\ReviewStatus;
use App\Models\AuditSample;
use App\Models\Screening;
use App\Models\ScreeningRuleState;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function decidedAuditSample(string $ruleId, bool $correct): AuditSample
{
    $screening = Screening::factory()->create([
        'recommendation' => ReviewStatus::Rejected,
        'triggered_rules' => [$ruleId],
    ]);

    return AuditSample::factory()->create([
        'screening_id' => $screening->id,
        'correct' => $correct,
        'decided_at' => now(),
    ]);
}

it('disables an auto-reject rule under 99% precision (FR-006-05, FR-006-20)', function () {
    $ruleId = ScreenReviewSubmission::RULE_BLOCKLIST_WORD;

    decidedAuditSample($ruleId, false);
    for ($i = 0; $i < 9; $i++) {
        decidedAuditSample($ruleId, true);
    }

    $precision = (new ComputeRulePrecision)->handle();

    expect($precision->get($ruleId))->toBe(0.9)
        ->and(ScreeningRuleState::isEnabled($ruleId))->toBeFalse();
});

it('leaves a rule enabled at or above 99% precision', function () {
    $ruleId = ScreenReviewSubmission::RULE_EXACT_DUPLICATE_CLUSTER;

    for ($i = 0; $i < 99; $i++) {
        decidedAuditSample($ruleId, true);
    }
    decidedAuditSample($ruleId, false);

    (new ComputeRulePrecision)->handle();

    expect(ScreeningRuleState::isEnabled($ruleId))->toBeTrue();
});

it('ignores a rule with no decided samples in the window', function () {
    $ruleId = ScreenReviewSubmission::RULE_BLOCKLIST_WORD;
    $screening = Screening::factory()->create(['recommendation' => ReviewStatus::Rejected, 'triggered_rules' => [$ruleId]]);
    AuditSample::factory()->create(['screening_id' => $screening->id]);

    $precision = (new ComputeRulePrecision)->handle();

    expect($precision->has($ruleId))->toBeFalse()
        ->and(ScreeningRuleState::isEnabled($ruleId))->toBeTrue();
});

it('ignores decided samples outside the trailing window', function () {
    $ruleId = ScreenReviewSubmission::RULE_BLOCKLIST_WORD;
    $screening = Screening::factory()->create(['recommendation' => ReviewStatus::Rejected, 'triggered_rules' => [$ruleId]]);
    AuditSample::factory()->create([
        'screening_id' => $screening->id,
        'correct' => false,
        'decided_at' => now()->subDays(91),
    ]);

    $precision = (new ComputeRulePrecision)->handle();

    expect($precision->has($ruleId))->toBeFalse()
        ->and(ScreeningRuleState::isEnabled($ruleId))->toBeTrue();
});
