<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Plan D7. Every /business/{business}/... route runs this: it scopes every
 * permission/role check for the rest of the request to that one business
 * (forgetting to set the team is exactly the failure mode D7 calls out —
 * this is the one place it happens, so nothing downstream can forget it),
 * and rejects anyone with no role at all on that business before they can
 * reach the route.
 */
class SetPermissionTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if (! $business instanceof Business) {
            throw new NotFoundHttpException;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);

        $user = $request->user();

        if (! $user || $user->roles()->count() === 0) {
            abort(403);
        }

        return $next($request);
    }
}
