<?php

namespace App\Console\Commands;

use App\Services\SalesDocumentsImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSalesDocumentsCommand extends Command
{
    protected $signature = 'sync:sales-documents';

    protected $description = 'Importa ventas pendientes del ecommerce hacia Firebird.';

    public function handle(SalesDocumentsImportService $service): int
    {
        try {
            $result = $service->importPending('console');
        } catch (Throwable $e) {
            Log::error('Error importando ventas del ecommerce hacia Firebird.', [
                'error' => $e->getMessage(),
            ]);

            $this->error('Error importando ventas: '.$e->getMessage());

            return self::FAILURE;
        }

        Log::info($result['message'], [
            'received' => $result['received'],
            'synced' => $result['synced'],
            'failed' => $result['failed'],
        ]);
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
