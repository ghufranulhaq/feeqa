<?php

use App\Http\Controllers\Public\ReviewerProfileController;
use Illuminate\Support\Facades\Route;

Route::get('reviewers/{user}', [ReviewerProfileController::class, 'show'])->name('reviewers.show');
