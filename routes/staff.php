<?php

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
});
