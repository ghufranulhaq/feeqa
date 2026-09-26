<?php

use App\Http\Controllers\Staff\BusinessClaimReviewController;
use App\Http\Controllers\Staff\BusinessProfileChangeRequestController;
use App\Http\Controllers\Staff\StaffAccountController;
use App\Http\Middleware\StaffIpAllowList;
use Illuminate\Support\Facades\Route;

// FR-001-14: reachable only from allow-listed networks in production.
Route::middleware(['auth', StaffIpAllowList::class])->prefix('staff')->group(function () {
    Route::post('accounts', [StaffAccountController::class, 'store'])->name('staff.accounts.store');

    // FR-002-07.
    Route::post('profile-change-requests/{changeRequest}/approve', [BusinessProfileChangeRequestController::class, 'approve'])
        ->name('staff.profile-change-requests.approve');
    Route::post('profile-change-requests/{changeRequest}/reject', [BusinessProfileChangeRequestController::class, 'reject'])
        ->name('staff.profile-change-requests.reject');

    // FR-002-11(d), FR-002-14.
    Route::post('business-claims/{claim}/approve', [BusinessClaimReviewController::class, 'approve'])
        ->name('staff.business-claims.approve');
    Route::post('business-claims/{claim}/reject', [BusinessClaimReviewController::class, 'reject'])
        ->name('staff.business-claims.reject');
});
