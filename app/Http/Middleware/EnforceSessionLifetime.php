<?php

namespace App\Http\Middleware;

use App\Support\SessionLifetime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-001-16. Independent of the framework's own SESSION_LIFETIME (config/
 * session.php, kept generous so it never cuts a session short before this
 * gets to decide) — this is the actual idle-timeout enforcement, and it
 * varies per user (SessionLifetime::minutesFor()).
 */
class EnforceSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $limit = SessionLifetime::minutesFor($user);
            $lastActivity = $request->session()->get('last_activity_at');

            if ($lastActivity && Carbon::parse($lastActivity)->diffInMinutes(now()) > $limit) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('status', 'Your session expired due to inactivity. Please sign in again.');
            }

            $request->session()->put('last_activity_at', now()->toIso8601String());
        }

        return $next($request);
    }
}
