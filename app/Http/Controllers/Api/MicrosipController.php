<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class MicrosipController extends Controller
{
    public function ping(): JsonResponse
    {
        try {
            DB::connection('firebird')->selectOne('SELECT 1 FROM RDB$DATABASE');

            return response()->json([
                'ok' => true,
                'message' => 'Microsip API autenticada y Firebird conectado.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Microsip API autenticada, pero Firebird no respondio.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
