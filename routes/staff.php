<?php

use App\Http\Controllers\Staff\StaffAccountController;
use App\Http\Middleware\StaffIpAllowList;
use Illuminate\Support\Facades\Route;

// FR-001-14: reachable only from allow-listed networks in production.
Route::middleware(['auth', StaffIpAllowList::class])->prefix('staff')->group(function () {
    Route::post('accounts', [StaffAccountController::class, 'store'])->name('staff.accounts.store');
});
