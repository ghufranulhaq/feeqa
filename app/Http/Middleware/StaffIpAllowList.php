<?php

namespace App\Http\Middleware;

use App\Support\Environment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * FR-001-14: "the staff console is reachable only from allow-listed
 * networks." Only enforced in production (Environment::
 * staffIpAllowlistEnforced(), constitution §5.6/plan D7) — any IP is fine
 * in demo/local/testing.
 */
class StaffIpAllowList
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Environment::staffIpAllowlistEnforced()) {
            return $next($request);
        }

        $allowed = config('platform.staff.allowed_ips');
        $ip = $request->ip();

        if ($allowed !== [] && $ip !== null && IpUtils::checkIp($ip, $allowed)) {
            return $next($request);
        }

        abort(403, 'This network is not allowed to reach the staff console.');
    }
}
