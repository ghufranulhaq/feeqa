<?php

use App\Http\Controllers\Public\BusinessClaimController;
use App\Http\Controllers\Public\BusinessCreateController;
use App\Http\Controllers\Public\BusinessProfileController;
use App\Http\Controllers\Public\ReviewerProfileController;
use Illuminate\Support\Facades\Route;

Route::get('reviewers/{user}', [ReviewerProfileController::class, 'show'])->name('reviewers.show');

// FR-002-08: signed-in only. Plural `/businesses/...`, distinct from the
// singular `/business/{slug}` profile route below, so "new" can never be
// mistaken for a slug.
Route::middleware('auth')->group(function () {
    Route::get('businesses/new', [BusinessCreateController::class, 'create'])->name('businesses.create');
    Route::post('businesses', [BusinessCreateController::class, 'store'])->name('businesses.store');

    // FR-002-11 through FR-002-15. {business} here binds by ID (Eloquent's
    // default), unrelated to the {slug} route below.
    Route::post('businesses/{business}/claim', [BusinessClaimController::class, 'store'])->name('businesses.claim.store');
    Route::post('business-claims/{claim}/verify-code', [BusinessClaimController::class, 'verifyCode'])->name('business-claims.verify-code');
    Route::post('business-claims/{claim}/verify-domain', [BusinessClaimController::class, 'verifyDomain'])->name('business-claims.verify-domain');
    Route::post('business-claims/{claim}/respond', [BusinessClaimController::class, 'respond'])->name('business-claims.respond');
});

// FR-002-02: {slug}, not an implicit {business} model binding — a slug
// that no longer matches any Business still needs to reach the
// controller so it can check business_slug_redirects before 404-ing.
Route::get('business/{slug}', [BusinessProfileController::class, 'show'])->name('businesses.show');
