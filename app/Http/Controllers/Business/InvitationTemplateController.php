<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\UpsertInvitationTemplate;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationTemplateController extends Controller
{
    public function store(Request $request, Business $business, UpsertInvitationTemplate $action): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'sender_name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email'],
        ]);

        $template = $action->handle(
            $business,
            $request->user(),
            $validated['locale'],
            $validated['subject'],
            $validated['body'],
            $validated['sender_name'] ?? null,
            $validated['reply_to'] ?? null,
        );

        return response()->json([
            'id' => $template->id,
            'locale' => $template->locale,
            'is_active' => $template->is_active,
        ]);
    }
}
