<?php

namespace App\Http\Controllers;

use App\Services\SalesDocumentsImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SalesDocumentsSyncController extends Controller
{
    public function run(SalesDocumentsImportService $service): RedirectResponse
    {
        try {
            $result = $service->importPending('dashboard');

            return redirect()
                ->route('support.dashboard')
                ->with('sales_documents_sync_result', [
                    'ok' => (bool) ($result['ok'] ?? false),
                    'message' => $result['message'] ?? 'Importacion ejecutada.',
                    'synced' => $result['synced'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'received' => $result['received'] ?? 0,
                ]);
        } catch (Throwable $e) {
            Log::error('Error ejecutando importacion DOCTOS_VE desde dashboard.', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('support.dashboard')
                ->with('sales_documents_sync_result', [
                    'ok' => false,
                    'message' => $e->getMessage(),
                    'synced' => 0,
                    'failed' => 0,
                    'received' => 0,
                ]);
        }
    }

    public function resolveFailure(int $item): RedirectResponse
    {
        DB::table('sales_document_sync_items')
            ->where('id', $item)
            ->where('status', 'failed')
            ->update([
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('support.dashboard')
            ->with('sales_documents_sync_result', [
                'ok' => true,
                'message' => 'Error de venta archivado del tablero.',
                'synced' => 0,
                'failed' => 0,
                'received' => 0,
            ]);
    }
}
