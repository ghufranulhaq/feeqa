<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewerProfileController extends Controller
{
    /**
     * FR-001-07: display name, avatar, country, member-since date, and
     * published review count/list only — never email, real name, or proof
     * data. Reviews are spec 003, so the list and count are always empty
     * until then.
     *
     * FR-001-20: "Public content is hidden right away" once deletion is
     * requested.
     */
    public function show(User $user): Response
    {
        if ($user->hasPendingDeletion()) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('public/reviewer-profile', [
            'reviewer' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_path' => $user->avatar_path,
                'country' => $user->country,
                'member_since' => $user->created_at->toDateString(),
                'reviews_count' => 0,
                'reviews' => [],
            ],
        ]);
    }
}
