<?php

use App\Http\Controllers\Business\BusinessInvitationAcceptController;
use App\Http\Controllers\Business\BusinessProfileController;
use App\Http\Controllers\Business\EmployeeSizeBandDisputeController;
use App\Http\Controllers\Business\InvitationController;
use App\Http\Controllers\Business\LocationController;
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
});
