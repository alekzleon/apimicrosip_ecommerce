<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EcommerceSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncEcommerceCommand extends Command
{
    protected $signature = 'sync:ecommerce';

    protected $description = 'Consulta registros pendientes de ECOMMER_SYNC en Firebird.';

    public function handle(EcommerceSyncService $syncService): int
    {
        try {
            $result = $syncService->checkPending('console');
        } catch (Throwable $e) {
            Log::error('Error consultando ECOMMER_SYNC en Firebird.', [
                'error' => $e->getMessage(),
            ]);

            $this->error('Error consultando ECOMMER_SYNC: '.$e->getMessage());

            return self::FAILURE;
        }

        Log::info($result['message'], [
            'total' => $result['total'],
            'should_continue' => $result['should_continue'],
        ]);
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
