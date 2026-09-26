<?php

use App\Http\Controllers\Public\BusinessClaimController;
use App\Http\Controllers\Public\BusinessClosureController;
use App\Http\Controllers\Public\BusinessCreateController;
use App\Http\Controllers\Public\BusinessProfileController;
use App\Http\Controllers\Public\LocationProfileController;
use App\Http\Controllers\Public\ReviewController;
use App\Http\Controllers\Public\ReviewDraftController;
use App\Http\Controllers\Public\ReviewerProfileController;
use App\Http\Controllers\Public\ReviewLifecycleUpdateController;
use App\Http\Controllers\Public\ReviewUsefulVoteController;
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

    // Edge cases table: reachable by an Owner or any staff member.
    Route::post('businesses/{business}/close', [BusinessClosureController::class, 'store'])->name('businesses.close');

    // FR-003-10: server-side draft autosave/restore. No submission form
    // reaches this yet, same situation as the claim/location endpoints above.
    Route::post('businesses/{business}/review-draft', [ReviewDraftController::class, 'store'])->name('businesses.review-draft.store');
    Route::get('businesses/{business}/review-draft', [ReviewDraftController::class, 'show'])->name('businesses.review-draft.show');

    // FR-003-27.
    Route::post('reviews/{review}/useful-vote', [ReviewUsefulVoteController::class, 'store'])->name('reviews.useful-vote.store');

    // FR-003-23 through FR-003-25: author-only edit/delete.
    Route::patch('reviews/{review}', [ReviewController::class, 'update'])->name('reviews.update');
    Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

    // FR-003-17 through FR-003-22.
    Route::post('reviews/{review}/lifecycle-updates', [ReviewLifecycleUpdateController::class, 'store'])
        ->name('reviews.lifecycle-updates.store');
});

// FR-002-16.
Route::get('business/{businessSlug}/locations/{locationSlug}', [LocationProfileController::class, 'show'])
    ->name('businesses.locations.show');

// FR-003-29: a permanent URL for one review, independent of the business
// profile's review list pagination.
Route::get('business/{slug}/reviews/{review}', [ReviewController::class, 'show'])->name('businesses.reviews.show');

// FR-002-02: {slug}, not an implicit {business} model binding — a slug
// that no longer matches any Business still needs to reach the
// controller so it can check business_slug_redirects before 404-ing.
Route::get('business/{slug}', [BusinessProfileController::class, 'show'])->name('businesses.show');
