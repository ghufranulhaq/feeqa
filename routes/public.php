<?php

use App\Http\Controllers\Public\BusinessProfileController;
use App\Http\Controllers\Public\ReviewerProfileController;
use Illuminate\Support\Facades\Route;

Route::get('reviewers/{user}', [ReviewerProfileController::class, 'show'])->name('reviewers.show');

// FR-002-02: {slug}, not an implicit {business} model binding — a slug
// that no longer matches any Business still needs to reach the
// controller so it can check business_slug_redirects before 404-ing.
Route::get('business/{slug}', [BusinessProfileController::class, 'show'])->name('businesses.show');
