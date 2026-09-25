<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\DataExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportDownloadController extends Controller
{
    public function __invoke(Request $request, DataExport $dataExport): StreamedResponse
    {
        abort_unless($request->user()->id === $dataExport->user_id, 403);
        abort_unless($dataExport->isDownloadable(), 404);

        return Storage::disk('local')->download($dataExport->file_path, 'my-data-export.zip');
    }
}
