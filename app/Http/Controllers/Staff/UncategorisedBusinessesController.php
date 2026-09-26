<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-002-35: "Uncategorised businesses go to a staff categorisation
 * queue, with a target of 5 business days." Read-only for any staff
 * role (FR-002-33) — assigning a real category is just the existing
 * App\Actions\Staff\UpdateCategoryDetails-adjacent business action
 * (updating `primary_category_id` directly; there's no dedicated
 * "categorise" action beyond that plain field update).
 */
class UncategorisedBusinessesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the uncategorised-business queue.');
        }

        $otherId = Category::where('slug', Category::OTHER_UNCATEGORISED_SLUG)->value('id');

        $businesses = Business::where('primary_category_id', $otherId)
            ->oldest()
            ->get(['id', 'name', 'slug', 'created_at']);

        return response()->json(['businesses' => $businesses]);
    }
}
