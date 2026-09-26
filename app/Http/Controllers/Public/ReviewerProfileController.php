<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use App\Support\Reviews\ReviewCard;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReviewerProfileController extends Controller
{
    /**
     * FR-001-07: display name, avatar, country, member-since date, and
     * published review count/list only — never email, real name, or proof
     * data. Wired to real data by spec 003 T6; capped at 50 (FR-003-29)
     * rather than fully paginated, since this page isn't one of T9's
     * "Business/Location" review lists.
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
                'reviews_count' => $user->reviews()->published()->count(),
                'reviews' => $user->reviews()
                    ->published()
                    ->with(['business', 'reviewer'])
                    ->latest('published_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (Review $review) => ReviewCard::present($review))
                    ->values(),
            ],
        ]);
    }
}
