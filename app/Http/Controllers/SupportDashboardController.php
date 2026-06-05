<?php

namespace App\Http\Controllers;

use App\Services\EcommerceSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class SupportDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $failureRunId = $request->integer('failure_run') ?: null;

        return view('support.dashboard', [
            'firebird' => $this->firebirdHealth(),
            'ecommerce' => $this->ecommerceHealth(),
            'queue' => $this->queueStats(),
            'recentRuns' => $this->recentRuns(),
            'recentFailures' => $this->recentFailures($failureRunId),
            'failureRunId' => $failureRunId,
            'lastRun' => session('support_sync_result'),
            'salesDocuments' => $this->salesDocumentsStats(),
            'recentSalesDocumentRuns' => $this->recentSalesDocumentRuns(),
            'recentSalesDocumentItems' => $this->recentSalesDocumentItems(),
            'lastSalesDocumentRun' => session('sales_documents_sync_result'),
        ]);
    }

    public function run(EcommerceSyncService $syncService): RedirectResponse
    {
        try {
            $result = $syncService->checkPending('dashboard');

            return redirect()
                ->route('support.dashboard')
                ->with('support_sync_result', [
                    'ok' => (bool) ($result['ok'] ?? false),
                    'message' => $result['message'] ?? 'Sincronizacion ejecutada.',
                    'items_synced' => $result['items_synced'] ?? 0,
                    'items_failed' => $result['items_failed'] ?? 0,
                    'rows_marked_as_synced' => $result['rows_marked_as_synced'] ?? 0,
                ]);
        } catch (Throwable $e) {
            Log::error('Error ejecutando sincronizacion desde dashboard de soporte.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('support.dashboard')
                ->with('support_sync_result', [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'items_synced' => 0,
                    'items_failed' => 0,
                    'rows_marked_as_synced' => 0,
                ]);
        }
    }

    public function resolveFailure(int $item): RedirectResponse
    {
        DB::table('sync_run_items')
            ->where('id', $item)
            ->where('status', 'failed')
            ->update([
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('support.dashboard')
            ->with('support_sync_result', [
                'ok' => true,
                'message' => 'Fallo archivado del tablero.',
                'items_synced' => 0,
                'items_failed' => 0,
                'rows_marked_as_synced' => 0,
            ]);
    }

    private function firebirdHealth(): array
    {
        try {
            DB::connection('firebird')->selectOne('SELECT 1 FROM RDB$DATABASE');

            return [
                'ok' => true,
                'label' => 'Conectado',
                'detail' => config('database.connections.firebird.host').':'.config('database.connections.firebird.port'),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'label' => 'Sin conexion',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function ecommerceHealth(): array
    {
        $baseUrl = rtrim((string) config('services.ecommerce_api.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->post($baseUrl.'/api/v1/login', [
                    'login' => config('services.ecommerce_api.login'),
                    'password' => config('services.ecommerce_api.password'),
                    'device_name' => config('services.ecommerce_api.device_name'),
                ]);

            return [
                'ok' => $response->successful() && filled($response->json('token')),
                'label' => $response->successful() ? 'Login OK' : 'Error '.$response->status(),
                'detail' => $baseUrl,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'label' => 'Sin conexion',
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function queueStats(): array
    {
        try {
            $rows = DB::connection('firebird')->select(<<<'SQL'
                SELECT
                    tipo,
                    sync,
                    COUNT(*) AS total
                FROM ECOMMER_SYNC
                GROUP BY tipo, sync
                ORDER BY tipo, sync
            SQL);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'pendingTotal' => 0,
                'syncedTotal' => 0,
                'rows' => [],
            ];
        }

        $stats = [];
        $pendingTotal = 0;
        $syncedTotal = 0;

        foreach ($rows as $row) {
            $type = (string) ($row->TIPO ?? $row->tipo ?? 'Sin tipo');
            $sync = $this->syncStatus($row->SYNC ?? $row->sync ?? null);
            $total = (int) ($row->TOTAL ?? $row->total ?? 0);

            $stats[$type] ??= [
                'type' => $type,
                'pending' => 0,
                'synced' => 0,
                'other' => 0,
            ];

            if ($sync === 'false') {
                $stats[$type]['pending'] += $total;
                $pendingTotal += $total;
            } elseif ($sync === 'true') {
                $stats[$type]['synced'] += $total;
                $syncedTotal += $total;
            } else {
                $stats[$type]['other'] += $total;
            }
        }

        return [
            'ok' => true,
            'error' => null,
            'pendingTotal' => $pendingTotal,
            'syncedTotal' => $syncedTotal,
            'rows' => array_values($stats),
        ];
    }

    private function syncStatus(mixed $value): string
    {
        if ($value === false || $value === 0) {
            return 'false';
        }

        if ($value === true || $value === 1) {
            return 'true';
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'false', '0', 'n', 'no' => 'false',
            'true', '1', 's', 'si', 'yes' => 'true',
            default => $normalized,
        };
    }

    private function recentRuns(): array
    {
        try {
            return DB::table('sync_runs')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'source' => $row->source,
                    'status' => $row->status,
                    'pending_selected' => (int) $row->pending_selected,
                    'items_synced' => (int) $row->items_synced,
                    'items_failed' => (int) $row->items_failed,
                    'rows_marked_as_synced' => (int) $row->rows_marked_as_synced,
                    'duration_ms' => $row->duration_ms,
                    'message' => $row->message,
                    'started_at' => $row->started_at,
                    'finished_at' => $row->finished_at,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function recentFailures(?int $runId = null): array
    {
        try {
            $query = DB::table('sync_run_items')
                ->where('status', 'failed')
                ->whereNull('resolved_at');

            if ($runId) {
                $query->where('sync_run_id', $runId);
            }

            return $query
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'run_id' => $row->sync_run_id,
                    'tipo' => $row->tipo,
                    'entity_id' => $row->entity_id,
                    'endpoint' => $row->endpoint,
                    'error' => $row->error,
                    'created_at' => $row->created_at,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function salesDocumentsStats(): array
    {
        try {
            return [
                'synced' => DB::table('sales_document_sync_items')->where('status', 'synced')->count(),
                'failed' => DB::table('sales_document_sync_items')->where('status', 'failed')->whereNull('resolved_at')->count(),
                'skipped' => DB::table('sales_document_sync_items')->where('status', 'skipped')->count(),
            ];
        } catch (Throwable) {
            return [
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }
    }

    private function recentSalesDocumentRuns(): array
    {
        try {
            return DB::table('sales_document_sync_runs')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'status' => $row->status,
                    'received' => (int) $row->received,
                    'synced' => (int) $row->synced,
                    'failed' => (int) $row->failed,
                    'duration_ms' => $row->duration_ms,
                    'started_at' => $row->started_at,
                    'message' => $row->message,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function recentSalesDocumentItems(): array
    {
        try {
            return DB::table('sales_document_sync_items')
                ->orderByDesc('id')
                ->limit(12)
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id,
                    'run_id' => $row->sales_document_sync_run_id,
                    'order_id' => $row->order_id,
                    'folio' => $row->folio,
                    'fecha' => $row->fecha,
                    'hora' => $row->hora,
                    'cliente_id' => $row->cliente_id,
                    'clave_cliente' => $row->clave_cliente,
                    'details_count' => (int) $row->details_count,
                    'status' => $row->status,
                    'error_stage' => $row->error_stage,
                    'error' => $row->error,
                    'resolved_at' => $row->resolved_at,
                    'created_at' => $row->created_at,
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

}
