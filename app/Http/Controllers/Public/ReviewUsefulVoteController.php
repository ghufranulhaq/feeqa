<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\ToggleUsefulVote;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * FR-003-27.
 */
class ReviewUsefulVoteController extends Controller
{
    /**
     * @return array{voted: bool, useful_count: int}
     */
    public function store(Request $request, Review $review, ToggleUsefulVote $action): array
    {
        $voted = $action->handle($request->user(), $review);

        return [
            'voted' => $voted,
            'useful_count' => $review->usefulVotes()->count(),
        ];
    }
}
