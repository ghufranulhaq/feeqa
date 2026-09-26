<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\SubmitTransactionRecords;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionRecordController extends Controller
{
    public function store(Request $request, Business $business, SubmitTransactionRecords $action): JsonResponse
    {
        $validated = $request->validate([
            'records' => ['required', 'array', 'max:'.config('platform.verification.transaction_records.max_per_request', 10000)],
            'records.*.reference_hash' => ['required', 'string'],
            'records.*.email_hash' => ['required', 'string'],
            'records.*.transaction_date' => ['required', 'date'],
            'records.*.skus' => ['nullable', 'array'],
        ]);

        $count = $action->handle($business, $request->user(), $validated['records']);

        return response()->json(['stored' => $count]);
    }
}
