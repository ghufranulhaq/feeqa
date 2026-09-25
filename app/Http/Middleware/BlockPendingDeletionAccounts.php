<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-001-20. A deletion request signs the user out immediately
 * (ProfileController::destroy()), but a still-live session on another
 * device shouldn't keep working for the 30 days until erasure — this
 * catches that on the very next request.
 */
class BlockPendingDeletionAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasPendingDeletion()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'This account has been deleted.');
        }

        return $next($request);
    }
}
