<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Accounts\RequestDataExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DataExportController extends Controller
{
    public function store(Request $request, RequestDataExport $action): RedirectResponse
    {
        $action->handle($request->user());

        return back()->with('status', "We're preparing your export. You'll get an email when it's ready.");
    }
}
