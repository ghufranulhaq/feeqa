<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\ImportBusinesses;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-002-24, FR-002-34. No staff console UI reaches this yet.
 */
class BusinessImportController extends Controller
{
    public function store(Request $request, ImportBusinesses $action): JsonResponse
    {
        $validated = $request->validate([
            'data_source' => ['required', 'string', 'max:255'],
            'import_batch' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.domain' => ['nullable', 'string', 'max:255'],
            'rows.*.country' => ['required', 'string', 'size:2'],
            'rows.*.primary_category_id' => ['required', Rule::exists(Category::class, 'id')],
            'rows.*.logo_path' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $action->handle(
            $request->user(),
            $validated['rows'],
            $validated['data_source'],
            $validated['import_batch'] ?? null,
        );

        return response()->json([
            'created' => $result->created->pluck('slug'),
            'skipped' => $result->skipped,
        ]);
    }
}
