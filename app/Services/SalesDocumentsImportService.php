<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SalesDocumentsImportService
{
    public function importPending(string $source = 'dashboard'): array
    {
        $startedAt = microtime(true);
        $runId = $this->startRun($source);

        try {
            $token = $this->login();
            $documents = $this->fetchPendingDocuments($token);
            $received = count($documents);
            $this->updateRun($runId, ['received' => $received]);

            $synced = 0;
            $failed = 0;

            foreach ($documents as $document) {
                $itemStartedAt = microtime(true);
                $itemId = $this->startItem($runId, $document);

                try {
                    if ($this->wasOrderAlreadySynced($document)) {
                        $this->finishItem($itemId, 'skipped', $itemStartedAt);

                        continue;
                    }

                    $microsipIds = $this->insertDocument($document);
                    $this->markDocumentSynced($token, $document, $microsipIds);
                    $this->finishItem($itemId, 'synced', $itemStartedAt);
                    $synced++;
                } catch (Throwable $e) {
                    $failed++;
                    $this->finishItem($itemId, 'failed', $itemStartedAt, $e, $this->errorStage($e));

                    Log::error('No se pudo importar DOCTOS_VE desde ecommerce.', [
                        'order_id' => $document['order_id'] ?? null,
                        'folio' => $document['doctos_ve']['FOLIO'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result = [
                'ok' => true,
                'message' => "Documentos VE recibidos: {$received}. Insertados: {$synced}. Fallidos: {$failed}.",
                'received' => $received,
                'synced' => $synced,
                'failed' => $failed,
            ];

            $this->finishRun($runId, $failed > 0 ? 'completed_with_errors' : 'completed', $result, $startedAt);

            return $result;
        } catch (Throwable $e) {
            $this->finishRun($runId, 'failed', [
                'message' => $e->getMessage(),
                'received' => 0,
                'synced' => 0,
                'failed' => 0,
            ], $startedAt, $e);

            throw $e;
        }
    }

    private function fetchPendingDocuments(string $token): array
    {
        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');
        $perPage = max(1, (int) config('services.sales_documents_sync.per_page', 50));

        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(60)
            ->get($baseUrl.'/api/v1/admin/sync/doctos-ve', [
                'sincronizado' => 0,
                'per_page' => $perPage,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('No se pudieron consultar DOCTOS_VE del ecommerce. Status: '.$response->status());
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('El ecommerce no regreso data valida para DOCTOS_VE.');
        }

        return $data;
    }

    private function login(): string
    {
        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(30)
            ->post($baseUrl.'/api/v1/login', [
                'login' => config('services.ecommerce_api.login'),
                'password' => config('services.ecommerce_api.password'),
                'device_name' => config('services.ecommerce_api.device_name'),
            ]);

        $token = $response->json('token');

        if ($response->failed() || ! $token) {
            throw new RuntimeException('No se pudo obtener token de ecommerce API para DOCTOS_VE.');
        }

        return (string) $token;
    }

    private function insertDocument(array $document): array
    {
        $header = $document['doctos_ve'] ?? null;
        $details = $document['doctos_ve_detalles'] ?? null;

        if (! is_array($header) || $header === []) {
            throw new RuntimeException('Documento sin encabezado DOCTOS_VE.');
        }

        if (! is_array($details) || $details === []) {
            throw new RuntimeException('Documento sin detalles DOCTOS_VE_DET.');
        }

        return DB::connection('firebird')->transaction(function () use ($header, $details): array {
            $doctoVeId = $this->insertFirebirdRecord('DOCTOS_VE', $header, 'DOCTO_VE_ID');
            $detailIds = [];

            foreach ($details as $index => $detail) {
                if (! is_array($detail)) {
                    throw new RuntimeException('Detalle DOCTOS_VE_DET invalido.');
                }

                $ecommerceDetailId = $detail['id'] ?? $detail['ID'] ?? $index;
                unset($detail['id'], $detail['ID']);

                if ($doctoVeId && empty($detail['DOCTO_VE_ID'])) {
                    $detail['DOCTO_VE_ID'] = $doctoVeId;
                }

                $detailIds[] = [
                    'id' => $ecommerceDetailId,
                    'docto_ve_det_id' => $this->insertFirebirdRecord('DOCTOS_VE_DET', $detail, 'DOCTO_VE_DET_ID'),
                ];
            }

            return [
                'docto_ve_id' => $doctoVeId,
                'detalles' => $detailIds,
            ];
        });
    }

    private function markDocumentSynced(string $token, array $document, array $microsipIds): void
    {
        $ecommerceDocumentId = $document['id'] ?? null;

        if (! $ecommerceDocumentId) {
            throw new RuntimeException('No se puede marcar DOCTOS_VE como sincronizado porque falta id del ecommerce.');
        }

        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');

        $response = Http::acceptJson()
            ->asJson()
            ->withToken($token)
            ->timeout(30)
            ->patch($baseUrl.'/api/v1/admin/sync/doctos-ve/'.$ecommerceDocumentId.'/sincronizado', [
                'docto_ve_id' => $microsipIds['docto_ve_id'] ?? null,
                'detalles' => $microsipIds['detalles'] ?? [],
                'microsip_response' => [
                    'message' => 'Insertado correctamente en Microsip desde API Raul.',
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Documento insertado en Firebird, pero no se pudo marcar como sincronizado en ecommerce. Status: '.$response->status());
        }
    }

    private function insertFirebirdRecord(string $table, array $record, string $returningField): mixed
    {
        $record = $this->cleanRecord($record);
        $returningField = strtoupper($returningField);

        $record = $this->applyFirebirdColumnAliases($table, $record);
        $record = $this->applyFirebirdDetailDefaults($table, $record);
        $record = $this->normalizeFirebirdRecord($table, $record);
        $record = $this->applyFirebirdGeneratedValues($table, $record, $returningField);

        if ($record === []) {
            throw new RuntimeException("Registro vacio para {$table}.");
        }

        $columns = array_keys($record);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
        $values = array_values($record);

        try {
            $row = DB::connection('firebird')->selectOne(
                "INSERT INTO {$table} ({$columnSql}) VALUES ({$placeholders}) RETURNING {$returningField}",
                $values
            );

            return $this->field($row, $returningField);
        } catch (Throwable $e) {
            throw new RuntimeException("Error insertando {$table}: ".$e->getMessage(), previous: $e);
        }
    }

    private function applyFirebirdColumnAliases(string $table, array $record): array
    {
        if (strtoupper($table) !== 'DOCTOS_VE_DET') {
            return $record;
        }

        $aliases = [
            'UNIDADES_COMPRO' => 'UNIDADES_COMPROM',
            'UNIDADES_COMPROMETIDAS' => 'UNIDADES_COMPROM',
            'UNIDADES_SURT_DE' => 'UNIDADES_SURT_DEV',
            'PCTJE_DSCTO_PROM' => 'PCTJE_DSCTO_PROMO',
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $record) && ! array_key_exists($to, $record)) {
                $record[$to] = $record[$from];
            }

            unset($record[$from]);
        }

        return $record;
    }

    private function applyFirebirdDetailDefaults(string $table, array $record): array
    {
        if (strtoupper($table) !== 'DOCTOS_VE_DET') {
            return $record;
        }

        $record['UNIDADES_COMPROM'] = 0.0;

        return $record;
    }

    private function normalizeFirebirdRecord(string $table, array $record): array
    {
        if (strtoupper($table) !== 'DOCTOS_VE_DET') {
            return $record;
        }

        foreach ($record as $column => $value) {
            if (! $this->shouldNormalizeFirebirdDetailDecimal((string) $column)) {
                continue;
            }

            $record[$column] = $this->normalizeFirebirdDecimal($value);
        }

        return $record;
    }

    private function shouldNormalizeFirebirdDetailDecimal(string $column): bool
    {
        $column = strtoupper($column);

        if (str_ends_with($column, '_ID')) {
            return false;
        }

        return str_starts_with($column, 'UNIDADES')
            || str_starts_with($column, 'PRECIO')
            || str_starts_with($column, 'PCTJE_')
            || str_contains($column, 'IMPORTE')
            || str_contains($column, 'TOTAL')
            || str_contains($column, 'DSCTO');
    }

    private function normalizeFirebirdDecimal(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if (! Str::isMatch('/^-?\d+(?:\.\d+)?$/', $trimmed)) {
            return $value;
        }

        return (float) $trimmed;
    }

    private function applyFirebirdGeneratedValues(string $table, array $record, string $returningField): array
    {
        if (
            in_array($returningField, ['DOCTO_VE_ID', 'DOCTO_VE_DET_ID'], true)
            && (! array_key_exists($returningField, $record) || $this->isBlankValue($record[$returningField]))
        ) {
            $record[$returningField] = -1;
        }

        if (
            strtoupper($table) === 'DOCTOS_VE_DET'
            && (! array_key_exists('POSICION', $record) || $this->isBlankValue($record['POSICION']))
        ) {
            $record['POSICION'] = -1;
        }

        return $record;
    }

    private function isBlankValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function cleanRecord(array $record): array
    {
        $clean = [];

        foreach ($record as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $clean[strtoupper($key)] = $this->cleanValue($value);
        }

        return $clean;
    }

    private function cleanValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (Str::isMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value)) {
            return str_replace('T', ' ', preg_replace('/(?:\.\d+)?Z$/', '', $value));
        }

        return $value;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return str_replace('"', '""', strtoupper($identifier));
    }

    private function wasOrderAlreadySynced(array $document): bool
    {
        $orderId = $document['order_id'] ?? null;

        if (! $orderId) {
            return false;
        }

        return DB::table('sales_document_sync_items')
            ->where('order_id', $orderId)
            ->whereIn('status', ['synced', 'skipped'])
            ->exists();
    }

    private function field(?object $row, string $name): mixed
    {
        if (! $row) {
            return null;
        }

        foreach ((array) $row as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    private function errorStage(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'DOCTOS_VE_DET')) {
            return 'detalle';
        }

        if (str_contains($message, 'DOCTOS_VE')) {
            return 'encabezado';
        }

        return 'general';
    }

    private function startRun(string $source): ?int
    {
        try {
            return (int) DB::table('sales_document_sync_runs')->insertGetId([
                'source' => $source,
                'status' => 'running',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('No se pudo iniciar auditoria de DOCTOS_VE.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function updateRun(?int $runId, array $values): void
    {
        if (! $runId) {
            return;
        }

        DB::table('sales_document_sync_runs')
            ->where('id', $runId)
            ->update(array_merge($values, ['updated_at' => now()]));
    }

    private function finishRun(?int $runId, string $status, array $result, float $startedAt, ?Throwable $error = null): void
    {
        if (! $runId) {
            return;
        }

        $this->updateRun($runId, [
            'status' => $status,
            'received' => (int) ($result['received'] ?? 0),
            'synced' => (int) ($result['synced'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
            'message' => $result['message'] ?? null,
            'error' => $error?->getMessage(),
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function startItem(?int $runId, array $document): ?int
    {
        if (! $runId) {
            return null;
        }

        $header = is_array($document['doctos_ve'] ?? null) ? $document['doctos_ve'] : [];
        $details = is_array($document['doctos_ve_detalles'] ?? null) ? $document['doctos_ve_detalles'] : [];

        return (int) DB::table('sales_document_sync_items')->insertGetId([
            'sales_document_sync_run_id' => $runId,
            'ecommerce_sync_id' => $document['id'] ?? null,
            'order_id' => $document['order_id'] ?? null,
            'sync_status' => $document['sync_status'] ?? null,
            'folio' => $header['FOLIO'] ?? null,
            'fecha' => $header['FECHA'] ?? null,
            'hora' => $header['HORA'] ?? null,
            'clave_cliente' => $header['CLAVE_CLIENTE'] ?? null,
            'cliente_id' => isset($header['CLIENTE_ID']) ? (string) $header['CLIENTE_ID'] : null,
            'details_count' => count($details),
            'status' => 'running',
            'validation_errors' => isset($document['validation_errors']) ? json_encode($document['validation_errors']) : null,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function finishItem(?int $itemId, string $status, float $startedAt, ?Throwable $error = null, ?string $stage = null): void
    {
        if (! $itemId) {
            return;
        }

        DB::table('sales_document_sync_items')
            ->where('id', $itemId)
            ->update([
                'status' => $status,
                'error_stage' => $stage,
                'error' => $error?->getMessage(),
                'finished_at' => now(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'updated_at' => now(),
            ]);
    }
}
