<?php

namespace App\Http\Controllers\Public;

use App\Drivers\Signing\SigningService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * FR-004-16: "old public keys stay published so older attestations can
 * still be checked." Public keys only — SigningService::publicKeys()
 * never returns a secret key.
 */
class PlatformKeysController extends Controller
{
    public function index(SigningService $signing): JsonResponse
    {
        return response()->json(['keys' => $signing->publicKeys()]);
    }
}
