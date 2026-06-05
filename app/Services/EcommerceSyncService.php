<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class EcommerceSyncService
{
    public function checkPending(string $source = 'system'): array
    {
        $startedAt = microtime(true);
        $runId = null;

        $batchLimit = max(1, (int) config('services.ecommerce_sync.batch_limit', 100));

        $rows = DB::connection('firebird')->select(<<<SQL
            SELECT FIRST {$batchLimit}
                es.RDB\$DB_KEY AS DB_KEY,
                es.*
            FROM ECOMMER_SYNC es
            WHERE sync = 'false'
        SQL);

        $total = count($rows);
        $shouldContinue = $total > 0;
        $runId = $this->startAuditRun($source, $total);

        if (! $shouldContinue) {
            $result = [
                'ok' => true,
                'message' => "ECOMMER_SYNC sin suficientes registros pendientes. Total: {$total}.",
                'total' => $total,
                'should_continue' => false,
                'items_synced' => 0,
                'items_failed' => 0,
                'data' => $this->normalizeUtf8($rows),
            ];

            $this->finishAuditRun($runId, 'completed', $result, $startedAt);

            return $result;
        }

        $items = $this->getSyncItemsFromPendingRows($rows);

        if ($items === []) {
            $message = 'ECOMMER_SYNC tiene pendientes, pero no se encontraron registros validos para sincronizar.';

            Log::warning($message, [
                'total' => $total,
            ]);

            $result = [
                'ok' => false,
                'message' => $message,
                'total' => $total,
                'should_continue' => true,
                'items_synced' => 0,
                'items_failed' => 0,
                'data' => $this->normalizeUtf8($rows),
            ];

            $this->finishAuditRun($runId, 'failed', $result, $startedAt);

            return $result;
        }

        try {
            $token = $this->login();
            $syncResults = $this->syncItemsInChunks($token, $items, $runId);

            $result = [
                'ok' => true,
                'message' => "ECOMMER_SYNC proceso {$total} registros pendientes. Sincronizados: {$syncResults['items_synced']}. Fallidos: {$syncResults['items_failed']}.",
                'total' => $total,
                'should_continue' => true,
                'items_synced' => $syncResults['items_synced'],
                'items_failed' => $syncResults['items_failed'],
                'rows_marked_as_synced' => $syncResults['rows_marked_as_synced'],
                'sync_results' => $syncResults['results'],
                'sync_failures' => $syncResults['failures'],
                'data' => $this->normalizeUtf8($rows),
            ];

            $this->finishAuditRun($runId, $syncResults['items_failed'] > 0 ? 'completed_with_errors' : 'completed', $result, $startedAt);

            return $result;
        } catch (Throwable $e) {
            $this->finishAuditRun($runId, 'failed', [
                'message' => $e->getMessage(),
                'items_synced' => 0,
                'items_failed' => count($items),
                'rows_marked_as_synced' => 0,
            ], $startedAt, $e);

            throw $e;
        }
    }

    private function getSyncItemsFromPendingRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $meta = $this->decodeMeta($row);
            $dbKey = $this->field($row, 'db_key');
            $type = $this->syncType($row);
            $config = $this->syncConfig($type);

            if (! $config) {
                Log::warning('Registro ECOMMER_SYNC con tipo no soportado.', [
                    'tipo' => $type,
                    'row' => (array) $row,
                ]);

                continue;
            }

            $entityId = $this->metaValue($meta, $config['id_field']);

            if (! $entityId) {
                Log::warning('Registro ECOMMER_SYNC sin id requerido en meta.', [
                    'tipo' => $type,
                    'id_field' => $config['id_field'],
                    'row' => (array) $row,
                ]);

                continue;
            }

            if (! $dbKey) {
                Log::warning('Registro ECOMMER_SYNC sin DB_KEY. No se enviara para evitar duplicados.', [
                    'tipo' => $type,
                    'entity_id' => $entityId,
                    'row' => (array) $row,
                ]);

                continue;
            }

            $record = DB::connection('firebird')->selectOne(
                $config['query'],
                [$entityId]
            );

            if (! $record) {
                Log::warning('No se encontro registro en Firebird para sincronizar.', [
                    'tipo' => $type,
                    'entity_id' => $entityId,
                ]);

                continue;
            }

            $items[] = [
                'type' => $type,
                'entity_id' => $entityId,
                'db_key' => $dbKey,
                'payload_key' => $config['payload_key'],
                'endpoint' => $config['endpoint'],
                'record' => $this->normalizeUtf8((array) $record),
            ];
        }

        return $items;
    }

    private function decodeMeta(object $row): array
    {
        $meta = $this->field($row, 'meta');

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (! is_string($meta) || trim($meta) === '') {
            return [];
        }

        try {
            $decoded = json_decode($meta, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('No se pudo decodificar meta de ECOMMER_SYNC.', [
                'meta' => $meta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function field(object $row, string $name): mixed
    {
        foreach ((array) $row as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function metaValue(array $meta, string $name): mixed
    {
        foreach ($meta as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function syncType(object $row): string
    {
        return strtolower(trim((string) $this->field($row, 'tipo')));
    }

    private function syncConfig(string $type): ?array
    {
        return match ($type) {
            'articulo' => [
                'id_field' => 'ARTICULO_ID',
                'query' => 'SELECT * FROM ARTICULOS WHERE ARTICULO_ID = ?',
                'endpoint' => '/api/v1/admin/sync/products',
                'payload_key' => 'products',
            ],
            'cliente' => [
                'id_field' => 'CLIENTE_ID',
                'query' => <<<'SQL'
                    SELECT
                        c.*,
                        (
                            SELECT FIRST 1 cc.CLAVE_CLIENTE
                            FROM CLAVES_CLIENTES cc
                            WHERE cc.CLIENTE_ID = c.CLIENTE_ID
                            ORDER BY cc.CLAVE_CLIENTE_ID
                        ) AS CLAVE_CLIENTE
                    FROM CLIENTES c
                    WHERE c.CLIENTE_ID = ?
                SQL,
                'endpoint' => '/api/v1/admin/sync/customers',
                'payload_key' => 'customers',
            ],
            'dircliente', 'dir_cliente', 'dirs_clientes', 'dirs_cliente', 'direccion_cliente' => [
                'id_field' => 'DIR_CLI_ID',
                'query' => 'SELECT * FROM DIRS_CLIENTES WHERE DIR_CLI_ID = ?',
                'endpoint' => '/api/v1/admin/sync/dirs-clientes',
                'payload_key' => 'dirs_clientes',
            ],
            'clave_cliente', 'claves_clientes' => [
                'id_field' => 'CLAVE_CLIENTE_ID',
                'query' => 'SELECT * FROM CLAVES_CLIENTES WHERE CLAVE_CLIENTE_ID = ?',
                'endpoint' => '/api/v1/admin/sync/claves-clientes',
                'payload_key' => 'claves_clientes',
            ],
            'claves_articulos', 'clave_articulo' => [
                'id_field' => 'CLAVE_ARTICULO_ID',
                'query' => 'SELECT * FROM CLAVES_ARTICULOS WHERE CLAVE_ARTICULO_ID = ?',
                'endpoint' => '/api/v1/admin/sync/claves-articulos',
                'payload_key' => 'claves_articulos',
            ],
            'precios_articulos', 'precio_articulo' => [
                'id_field' => 'PRECIO_ARTICULO_ID',
                'query' => 'SELECT * FROM PRECIOS_ARTICULOS WHERE PRECIO_ARTICULO_ID = ?',
                'endpoint' => '/api/v1/admin/sync/precios-articulos',
                'payload_key' => 'precios_articulos',
            ],
            'precios_empresa', 'precios_empresas', 'precio_empresa' => [
                'id_field' => 'PRECIO_EMPRESA_ID',
                'query' => 'SELECT * FROM PRECIOS_EMPRESA WHERE PRECIO_EMPRESA_ID = ?',
                'endpoint' => '/api/v1/admin/sync/precios-empresas',
                'payload_key' => 'precios_empresa',
            ],
            'precios_cli_cli', 'precio_cli_cli', 'precioclicli' => [
                'id_field' => 'PRECIO_CLI_CLI_ID',
                'query' => 'SELECT * FROM PRECIOS_CLI_CLI WHERE PRECIO_CLI_CLI_ID = ?',
                'endpoint' => '/api/v1/admin/sync/precios-cli-cli',
                'payload_key' => 'precios_cli_cli',
            ],
            'tipos_impuestos', 'tipo_impuesto' => [
                'id_field' => 'TIPO_IMPTO_ID',
                'query' => <<<'SQL'
                    SELECT
                        TIPO_IMPTO_ID,
                        NOMBRE,
                        TIPO,
                        GRAVA_OTROS_IMPTOS,
                        APLICA_SOLO_SOBRE_IMPTE_IMPTO AS APLICA_SOLO_SOBRE_IMPTE_IMP,
                        ID_INTERNO,
                        ES_PREDET,
                        USUARIO_CREADOR,
                        FECHA_HORA_CREACION,
                        USUARIO_AUT_CREACION,
                        USUARIO_ULT_MODIF,
                        FECHA_HORA_ULT_MODIF,
                        USUARIO_AUT_MODIF
                    FROM TIPOS_IMPUESTOS
                    WHERE TIPO_IMPTO_ID = ?
                SQL,
                'endpoint' => '/api/v1/admin/sync/tipos-impuestos',
                'payload_key' => 'tipos_impuestos',
            ],
            'impuestos' => [
                'id_field' => 'IMPUESTO_ID',
                'query' => 'SELECT * FROM IMPUESTOS WHERE IMPUESTO_ID = ?',
                'endpoint' => '/api/v1/admin/sync/impuestos',
                'payload_key' => 'impuestos',
            ],
            'impuestos_articulos', 'impuesto_articulo' => [
                'id_field' => 'IMPUESTO_ART_ID',
                'query' => 'SELECT * FROM IMPUESTOS_ARTICULOS WHERE IMPUESTO_ART_ID = ?',
                'endpoint' => '/api/v1/admin/sync/impuestos-articulos',
                'payload_key' => 'impuestos_articulos',
            ],
            'grupos_lineas', 'grupo_linea' => [
                'id_field' => 'GRUPO_LINEA_ID',
                'query' => 'SELECT * FROM GRUPOS_LINEAS WHERE GRUPO_LINEA_ID = ?',
                'endpoint' => '/api/v1/admin/sync/categories',
                'payload_key' => 'GRUPOS_LINEAS',
            ],
            'lineas_articulos', 'linea_articulo' => [
                'id_field' => 'LINEA_ARTICULO_ID',
                'query' => 'SELECT * FROM LINEAS_ARTICULOS WHERE LINEA_ARTICULO_ID = ?',
                'endpoint' => '/api/v1/admin/sync/families',
                'payload_key' => 'LINEAS_ARTICULOS',
            ],
            default => null,
        };
    }

    private function login(): string
    {
        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');
        $login = (string) config('services.ecommerce_api.login');
        $password = (string) config('services.ecommerce_api.password');
        $deviceName = (string) config('services.ecommerce_api.device_name');

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(30)
                ->post($baseUrl.'/api/v1/login', [
                    'login' => $login,
                    'password' => $password,
                    'device_name' => $deviceName,
                ]);
        } catch (Throwable $e) {
            Log::error('Error conectando con login de ecommerce API.', [
                'base_url' => $baseUrl,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $token = $response->json('token');

        if ($response->failed() || ! $token) {
            Log::error('Error obteniendo token de ecommerce API.', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException('No se pudo obtener token de ecommerce API.');
        }

        return (string) $token;
    }

    private function syncPayload(string $token, string $endpoint, string $payloadKey, array $records): array
    {
        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->timeout(60)
                ->post($baseUrl.$endpoint, [
                    $payloadKey => $this->normalizeUtf8($records),
                ]);
        } catch (Throwable $e) {
            Log::error('Error conectando con sync de ecommerce API.', [
                'base_url' => $baseUrl,
                'endpoint' => $endpoint,
                'payload_key' => $payloadKey,
                'records_count' => count($records),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($response->failed()) {
            Log::error('Error sincronizando registros con ecommerce API.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            throw new RuntimeException('No se pudieron sincronizar registros con ecommerce API.');
        }

        $body = $response->json();

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            Log::error('Ecommerce API no regreso ok=true al sincronizar registros.', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $body ?? $response->body(),
            ]);

            throw new RuntimeException('Ecommerce API no confirmo la sincronizacion de registros.');
        }

        return [
            'status' => $response->status(),
            'body' => $body,
        ];
    }

    private function syncItemsInChunks(string $token, array $items, ?int $runId = null): array
    {
        $chunkSize = max(1, (int) config('services.ecommerce_sync.http_chunk_size', 100));
        $synced = 0;
        $failed = 0;
        $marked = 0;
        $results = [];
        $failures = [];

        $groups = [];

        foreach ($items as $item) {
            $key = $item['endpoint'].'|'.$item['payload_key'];
            $groups[$key][] = $item;
        }

        foreach ($groups as $groupItems) {
            foreach (array_chunk($groupItems, $chunkSize) as $chunk) {
                $chunkStartedAt = microtime(true);
                $auditItemIds = [];

                foreach ($chunk as $item) {
                    $auditItemIds[] = $this->startAuditItem($runId, $item);
                }

                try {
                    $syncResponse = $this->syncPayload(
                        $token,
                        $chunk[0]['endpoint'],
                        $chunk[0]['payload_key'],
                        array_column($chunk, 'record')
                    );

                    $marked += $this->markRowsAsSynced(array_column($chunk, 'db_key'));
                    $synced += count($chunk);

                    foreach ($chunk as $index => $item) {
                        $results[] = [
                            'tipo' => $item['type'],
                            'entity_id' => $item['entity_id'],
                            'ok' => true,
                            'sync_response' => $syncResponse,
                        ];

                        $this->finishAuditItem($auditItemIds[$index] ?? null, 'synced', $chunkStartedAt, $syncResponse);
                    }
                } catch (Throwable $e) {
                    Log::warning('No se pudo sincronizar chunk. Se intentara registro por registro.', [
                        'endpoint' => $chunk[0]['endpoint'] ?? null,
                        'payload_key' => $chunk[0]['payload_key'] ?? null,
                        'records_count' => count($chunk),
                        'error' => $e->getMessage(),
                    ]);

                    foreach ($auditItemIds as $auditItemId) {
                        $this->deleteAuditItem($auditItemId);
                    }

                    $individual = $this->syncItemsIndividually($token, $chunk, $runId);
                    $synced += $individual['items_synced'];
                    $failed += $individual['items_failed'];
                    $marked += $individual['rows_marked_as_synced'];
                    $results = array_merge($results, $individual['results']);
                    $failures = array_merge($failures, $individual['failures']);
                }
            }
        }

        return [
            'items_synced' => $synced,
            'items_failed' => $failed,
            'rows_marked_as_synced' => $marked,
            'results' => $results,
            'failures' => $failures,
        ];
    }

    private function syncItemsIndividually(string $token, array $items, ?int $runId = null): array
    {
        $synced = 0;
        $failed = 0;
        $marked = 0;
        $results = [];
        $failures = [];

        foreach ($items as $item) {
            $itemStartedAt = microtime(true);
            $auditItemId = $this->startAuditItem($runId, $item);

            try {
                $syncResponse = $this->syncPayload(
                    $token,
                    $item['endpoint'],
                    $item['payload_key'],
                    [$item['record']]
                );
                $marked += $this->markRowsAsSynced([$item['db_key']]);
                $synced++;

                $results[] = [
                    'tipo' => $item['type'],
                    'entity_id' => $item['entity_id'],
                    'ok' => true,
                    'sync_response' => $syncResponse,
                ];

                $this->finishAuditItem($auditItemId, 'synced', $itemStartedAt, $syncResponse);
            } catch (Throwable $e) {
                $failed++;

                Log::error('No se pudo sincronizar registro. Se continua con el siguiente.', [
                    'tipo' => $item['type'],
                    'entity_id' => $item['entity_id'],
                    'error' => $e->getMessage(),
                ]);

                $failures[] = [
                    'tipo' => $item['type'],
                    'entity_id' => $item['entity_id'],
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];

                $this->finishAuditItem($auditItemId, 'failed', $itemStartedAt, null, $e);
            }
        }

        return [
            'items_synced' => $synced,
            'items_failed' => $failed,
            'rows_marked_as_synced' => $marked,
            'results' => $results,
            'failures' => $failures,
        ];
    }

    private function startAuditRun(string $source, int $pendingSelected): ?int
    {
        try {
            return (int) DB::table('sync_runs')->insertGetId([
                'source' => $source,
                'status' => 'running',
                'pending_selected' => $pendingSelected,
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo iniciar auditoria de sincronizacion.', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function finishAuditRun(?int $runId, string $status, array $result, float $startedAt, ?Throwable $error = null): void
    {
        if (! $runId) {
            return;
        }

        try {
            DB::table('sync_runs')
                ->where('id', $runId)
                ->update([
                    'status' => $status,
                    'items_synced' => (int) ($result['items_synced'] ?? 0),
                    'items_failed' => (int) ($result['items_failed'] ?? 0),
                    'rows_marked_as_synced' => (int) ($result['rows_marked_as_synced'] ?? 0),
                    'message' => $result['message'] ?? null,
                    'error' => $error?->getMessage(),
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo finalizar auditoria de sincronizacion.', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function startAuditItem(?int $runId, array $item): ?int
    {
        if (! $runId) {
            return null;
        }

        try {
            return (int) DB::table('sync_run_items')->insertGetId([
                'sync_run_id' => $runId,
                'tipo' => $item['type'] ?? null,
                'entity_id' => isset($item['entity_id']) ? (string) $item['entity_id'] : null,
                'endpoint' => $item['endpoint'] ?? null,
                'payload_key' => $item['payload_key'] ?? null,
                'db_key' => isset($item['db_key']) ? base64_encode((string) $item['db_key']) : null,
                'status' => 'running',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo iniciar auditoria de item sincronizado.', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function finishAuditItem(?int $itemId, string $status, float $startedAt, ?array $syncResponse = null, ?Throwable $error = null): void
    {
        if (! $itemId) {
            return;
        }

        try {
            DB::table('sync_run_items')
                ->where('id', $itemId)
                ->update([
                    'status' => $status,
                    'http_status' => $syncResponse['status'] ?? null,
                    'error' => $error?->getMessage(),
                    'ecommerce_response' => $syncResponse ? json_encode($syncResponse['body'] ?? null) : null,
                    'finished_at' => now(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo finalizar auditoria de item sincronizado.', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deleteAuditItem(?int $itemId): void
    {
        if (! $itemId) {
            return;
        }

        try {
            DB::table('sync_run_items')
                ->where('id', $itemId)
                ->delete();
        } catch (Throwable $e) {
            Log::warning('No se pudo eliminar auditoria temporal de item sincronizado.', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function markRowsAsSynced(array $dbKeys): int
    {
        $marked = 0;

        foreach ($dbKeys as $dbKey) {
            if (! $dbKey) {
                Log::warning('No se pudo marcar ECOMMER_SYNC como sincronizado porque no hay DB_KEY.');

                continue;
            }

            $marked += DB::connection('firebird')->update(
                'UPDATE ECOMMER_SYNC SET sync = ? WHERE RDB$DB_KEY = ?',
                ['true', $dbKey]
            );
        }

        return $marked;
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
            Log::warning('Se encontro texto con codificacion invalida y se limpio para JSON.');

            return mb_scrub($converted, 'UTF-8');
        }

        return $converted;
    }
}
