<?php

use App\Domain\Verification\EvaluateAutoApproval;
use App\Domain\Verification\TamperSignalResult;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-09-26 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

function evaluateAutoApproval(
    bool $merchantMatches = true,
    ?Carbon $transactionDate = null,
    ?Carbon $dateOfExperience = null,
    bool $referenceAlreadyUsed = false,
    ?TamperSignalResult $tamperSignals = null,
) {
    return (new EvaluateAutoApproval)->handle(
        $merchantMatches,
        $transactionDate ?? Carbon::parse('2026-09-01'),
        $dateOfExperience ?? Carbon::parse('2026-09-01'),
        $referenceAlreadyUsed,
        $tamperSignals ?? TamperSignalResult::pass(),
    );
}

it('approves when every condition holds (FR-004-06)', function () {
    $outcome = evaluateAutoApproval();

    expect($outcome->approved)->toBeTrue()
        ->and($outcome->reasonCode)->toBeNull();
});

it('holds on a merchant mismatch', function () {
    $outcome = evaluateAutoApproval(merchantMatches: false);

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('merchant_mismatch');
});

it('holds when there is no extracted transaction date', function () {
    $outcome = (new EvaluateAutoApproval)->handle(
        true,
        null,
        Carbon::parse('2026-09-01'),
        false,
        TamperSignalResult::pass(),
    );

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('date_out_of_window');
});

it('holds when the transaction is more than 12 months old', function () {
    $outcome = evaluateAutoApproval(
        transactionDate: Carbon::now()->subMonths(13),
        dateOfExperience: Carbon::now()->subMonths(13),
    );

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('date_out_of_window');
});

it('approves a transaction dated exactly at the 12 month boundary', function () {
    $date = Carbon::now()->subMonths(12);

    $outcome = evaluateAutoApproval(transactionDate: $date, dateOfExperience: $date);

    expect($outcome->approved)->toBeTrue();
});

it('holds when the transaction date is more than 30 days after the date of experience', function () {
    $experience = Carbon::parse('2026-06-01');

    $outcome = evaluateAutoApproval(
        transactionDate: $experience->copy()->addDays(31),
        dateOfExperience: $experience,
    );

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('date_out_of_window');
});

it('approves a transaction dated exactly 30 days after the date of experience', function () {
    $experience = Carbon::parse('2026-06-01');

    $outcome = evaluateAutoApproval(
        transactionDate: $experience->copy()->addDays(30),
        dateOfExperience: $experience,
    );

    expect($outcome->approved)->toBeTrue();
});

it('holds when the proof fingerprint has already been used (FR-004-10)', function () {
    $outcome = evaluateAutoApproval(referenceAlreadyUsed: true);

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('reference_reused');
});

it('holds with the specific tamper reason when a tamper check fails (FR-004-07)', function () {
    $outcome = evaluateAutoApproval(tamperSignals: TamperSignalResult::fail('editing_software_detected'));

    expect($outcome->approved)->toBeFalse()
        ->and($outcome->reasonCode)->toBe('editing_software_detected');
});

it('checks merchant match before the date window', function () {
    $outcome = evaluateAutoApproval(merchantMatches: false, transactionDate: null);

    expect($outcome->reasonCode)->toBe('merchant_mismatch');
});
