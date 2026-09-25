<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Accounts\UpdateAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function store(AvatarUpdateRequest $request, UpdateAvatar $action): RedirectResponse
    {
        $action->handle($request->user(), $request->file('avatar'));

        return to_route('profile.edit');
    }

    public function destroy(Request $request, UpdateAvatar $action): RedirectResponse
    {
        $action->remove($request->user());

        return to_route('profile.edit');
    }
}
