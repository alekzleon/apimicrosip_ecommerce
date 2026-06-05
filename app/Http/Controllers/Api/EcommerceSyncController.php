<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EcommerceSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class EcommerceSyncController extends Controller
{
    public function __construct(
        private readonly EcommerceSyncService $syncService
    ) {
    }

    public function check(): JsonResponse
    {
        try {
            $result = $this->syncService->checkPending('api');

            return response()->json($result);
        } catch (Throwable $e) {
            Log::error('Error ejecutando sincronizacion ecommerce desde API.', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Error ejecutando sincronizacion ecommerce',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
