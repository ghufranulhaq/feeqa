<?php

use App\Http\Controllers\Business\BccInvitationController;
use App\Http\Controllers\Business\BusinessInvitationAcceptController;
use App\Http\Controllers\Business\BusinessProfileController;
use App\Http\Controllers\Business\EmployeeSizeBandDisputeController;
use App\Http\Controllers\Business\InvitationController;
use App\Http\Controllers\Business\InvitationTemplateController;
use App\Http\Controllers\Business\LocationController;
use App\Http\Controllers\Business\ReviewInvitationController;
use App\Http\Controllers\Business\TransactionRecordController;
use App\Http\Controllers\Business\VerificationRequestController;
use App\Http\Middleware\SetPermissionTeam;
use Illuminate\Support\Facades\Route;

// Accepting an invitation happens before the invitee has any role on the
// business, so this can't go through SetPermissionTeam (it 403s anyone
// with no role at all).
Route::middleware('auth')->group(function () {
    Route::get('invitations/{token}', [BusinessInvitationAcceptController::class, 'show'])
        ->name('business-invitations.show');

    Route::post('invitations/{token}/accept', [BusinessInvitationAcceptController::class, 'accept'])
        ->name('business-invitations.accept');
});

Route::middleware(['auth', SetPermissionTeam::class])->prefix('business/{business}')->group(function () {
    Route::post('invitations', [InvitationController::class, 'store'])
        ->name('business.invitations.store');

    // FR-002-03, FR-002-07.
    Route::patch('profile', [BusinessProfileController::class, 'update'])
        ->name('business.profile.update');
    Route::post('logo', [BusinessProfileController::class, 'updateLogo'])
        ->name('business.logo.update');

    // FR-002-16.
    Route::post('locations', [LocationController::class, 'store'])->name('business.locations.store');
    Route::patch('locations/{location}', [LocationController::class, 'update'])->name('business.locations.update');
    Route::delete('locations/{location}', [LocationController::class, 'destroy'])->name('business.locations.destroy');

    // FR-002-25 edge case.
    Route::post('employee-size-band-disputes', [EmployeeSizeBandDisputeController::class, 'store'])
        ->name('business.employee-size-band-disputes.store');

    // FR-004-12: the "Business API" data shape for submitting hashed
    // transaction records, reachable from the dashboard today.
    Route::post('transaction-records', [TransactionRecordController::class, 'store'])
        ->name('business.transaction-records.store');

    // FR-004-18, FR-004-21.
    Route::post('reviews/{review}/verification-request', [VerificationRequestController::class, 'store'])
        ->name('business.reviews.verification-request.store');

    // FR-005-13, FR-005-14: one editable template per locale, checked by
    // GuardNeutralTemplate on every write.
    Route::post('invitation-templates', [InvitationTemplateController::class, 'store'])
        ->name('business.invitation-templates.store');

    // FR-005-01 (manual), FR-005-12. Distinct path/name from the
    // team-member `invitations` routes above — same word, unrelated
    // concept.
    Route::post('review-invitations', [ReviewInvitationController::class, 'store'])
        ->name('business.review-invitations.store');
    Route::delete('review-invitations/{reviewInvitation}', [ReviewInvitationController::class, 'destroy'])
        ->name('business.review-invitations.destroy');

    // FR-005-01 (csv), FR-005-03.
    Route::post('review-invitations/csv', [ReviewInvitationController::class, 'importCsv'])
        ->name('business.review-invitations.import-csv');

    // FR-005-01 (api), FR-005-02: the "Invitation API" data shape, reachable
    // from the dashboard today — see 004's own Business API stand-in.
    Route::post('review-invitations/api', [ReviewInvitationController::class, 'storeApi'])
        ->name('business.review-invitations.store-api');

    // FR-005-01 (bcc), FR-005-06, FR-005-07: a demo-only stand-in for real
    // inbound mail (plan D14), plus the method's own settings.
    Route::post('bcc-invitations/email', [BccInvitationController::class, 'importEmail'])
        ->name('business.bcc-invitations.import-email');
    Route::post('bcc-invitations/rotate-address', [BccInvitationController::class, 'rotateAddress'])
        ->name('business.bcc-invitations.rotate-address');
    Route::patch('bcc-invitations/settings', [BccInvitationController::class, 'updateSettings'])
        ->name('business.bcc-invitations.update-settings');
});
