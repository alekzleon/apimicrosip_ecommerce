<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class FirebirdController extends Controller
{
    /**
     * Prueba básica de conexión contra Firebird.
     */
    public function health(): JsonResponse
    {
        try {
            $row = DB::connection('firebird')->selectOne(
                'SELECT CURRENT_TIMESTAMP AS SERVER_TIME FROM RDB$DATABASE'
            );

            return response()->json([
                'ok' => true,
                'message' => 'Conexión Firebird correcta',
                'data' => $row,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error conectando con Firebird',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista tablas de usuario de la base Firebird.
     */
    public function tables(): JsonResponse
    {
        try {
            $tables = DB::connection('firebird')->select("
                SELECT
                    TRIM(RDB\$RELATION_NAME) AS TABLE_NAME
                FROM RDB\$RELATIONS
                WHERE
                    RDB\$SYSTEM_FLAG = 0
                    AND RDB\$VIEW_BLR IS NULL
                ORDER BY RDB\$RELATION_NAME
            ");

            return response()->json([
                'ok' => true,
                'data' => $tables,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudieron obtener las tablas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta rápida de una tabla.
     * Solo para pruebas internas. No usar abierto en producción.
     */
    public function table(string $table): JsonResponse
    {
        try {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Nombre de tabla inválido',
                ], 422);
            }

            $table = strtoupper($table);

            $rows = DB::connection('firebird')->select(
                "SELECT FIRST 20 * FROM {$table}"
            );

            return response()->json([
                'ok' => true,
                'table' => $table,
                'data' => $rows,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar la tabla',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}