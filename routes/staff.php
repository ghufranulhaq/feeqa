<?php

use App\Http\Controllers\Staff\BusinessClaimReviewController;
use App\Http\Controllers\Staff\BusinessImportController;
use App\Http\Controllers\Staff\BusinessMergeController;
use App\Http\Controllers\Staff\BusinessProfileChangeRequestController;
use App\Http\Controllers\Staff\CategoryController;
use App\Http\Controllers\Staff\EmployeeSizeBandDisputeController as StaffEmployeeSizeBandDisputeController;
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

    // FR-002-28, FR-002-30, FR-002-33.
    Route::post('categories', [CategoryController::class, 'store'])->name('staff.categories.store');
    Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('staff.categories.update');
    Route::post('categories/{industry}/launch', [CategoryController::class, 'launch'])->name('staff.categories.launch');
    Route::post('categories/{industry}/pause', [CategoryController::class, 'pause'])->name('staff.categories.pause');
    Route::post('categories/{category}/set-launched', [CategoryController::class, 'setLaunched'])->name('staff.categories.set-launched');
    // FR-002-32.
    Route::get('categories/{industry}/preview', [CategoryController::class, 'preview'])->name('staff.categories.preview');

    // FR-002-36, edge cases table.
    Route::patch('categories/{category}/slug', [CategoryController::class, 'renameSlug'])->name('staff.categories.rename-slug');
    Route::post('categories/{category}/move-businesses', [CategoryController::class, 'moveBusinesses'])->name('staff.categories.move-businesses');
    Route::post('categories/{category}/merge', [CategoryController::class, 'merge'])->name('staff.categories.merge');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('staff.categories.destroy');

    // FR-002-24, FR-002-34.
    Route::post('businesses/import', [BusinessImportController::class, 'store'])->name('staff.businesses.import');

    // Edge cases table: duplicate businesses merged.
    Route::post('businesses/{business}/merge', [BusinessMergeController::class, 'store'])->name('staff.businesses.merge');

    // FR-002-25 edge case.
    Route::post('employee-size-band-disputes/{dispute}/resolve', [StaffEmployeeSizeBandDisputeController::class, 'resolve'])
        ->name('staff.employee-size-band-disputes.resolve');
});
