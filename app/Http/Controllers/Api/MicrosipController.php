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

    public function purchasedItems(string $clienteId): JsonResponse
    {
        if (! ctype_digit($clienteId)) {
            return response()->json([
                'ok' => false,
                'message' => 'cliente_id invalido',
            ], 422);
        }

        $today = now();
        $fechaHoy = $today->toDateString();
        $fechaTresMesesAtras = $today->copy()->subMonthsNoOverflow(3)->toDateString();

        try {
            $rows = DB::connection('firebird')->select(<<<'SQL'
               SELECT DISTINCT
                dvd.CLAVE_ARTICULO,
                dvd.ARTICULO_ID
            FROM DOCTOS_VE_DET dvd
            JOIN DOCTOS_VE dv ON dv.DOCTO_VE_ID = dvd.DOCTO_VE_ID
            WHERE dv.CLIENTE_ID = ?
                AND dv.FECHA BETWEEN ? AND ?
                AND dv.TIPO_DOCTO = 'P'
            ORDER BY dvd.CLAVE_ARTICULO
            SQL, [
                (int) $clienteId,
                $fechaTresMesesAtras,
                $fechaHoy,
            ]);

            return response()->json([
                'ok' => true,
                'cliente_id' => (int) $clienteId,
                'fecha_inicio' => $fechaTresMesesAtras,
                'fecha_fin' => $fechaHoy,
                'data' => $this->normalizeUtf8($rows),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudieron consultar articulos comprados del cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function customerCharges(string $clienteId): JsonResponse
    {
        if (! ctype_digit($clienteId)) {
            return response()->json([
                'ok' => false,
                'message' => 'cliente_id invalido',
            ], 422);
        }

        $fechaHoy = now()->toDateString();
        $fechaFinal = '12-31-9999';

        try {
            $rows = DB::connection('firebird')->select(<<<'SQL'
                SELECT * FROM CARGOS_CLIENTE (?, ?, ?, ?, ?)
            SQL, [
                (int) $clienteId,
                $fechaHoy,
                $fechaFinal,
                'S',
                'S',
            ]);

            dd($rows);

            return response()->json([
                'ok' => true,
                'cliente_id' => (int) $clienteId,
                'fecha' => $fechaHoy,
                'fecha_final' => $fechaFinal,
                'data' => $this->normalizeUtf8($rows),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudieron consultar cargos del cliente.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function normalizeUtf8(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeUtf8($item), $value);
        }

        if (is_object($value)) {
            return $this->normalizeUtf8((array) $value);
        }

        if (! is_string($value) || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252,ISO-8859-1,UTF-8');

        if (! mb_check_encoding($converted, 'UTF-8')) {
            return mb_scrub($converted, 'UTF-8');
        }

        return $converted;
    }
}
