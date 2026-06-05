<?php

namespace Tests\Unit;

use App\Services\EcommerceSyncService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EcommerceSyncServiceTest extends TestCase
{
    #[DataProvider('clavesClientesTypes')]
    public function test_claves_clientes_sync_config_uses_expected_endpoint_and_payload(string $type): void
    {
        $config = $this->syncConfig($type);

        $this->assertSame('CLAVE_CLIENTE_ID', $config['id_field']);
        $this->assertSame('SELECT * FROM CLAVES_CLIENTES WHERE CLAVE_CLIENTE_ID = ?', $config['query']);
        $this->assertSame('/api/v1/admin/sync/claves-clientes', $config['endpoint']);
        $this->assertSame('claves_clientes', $config['payload_key']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function clavesClientesTypes(): array
    {
        return [
            'singular' => ['clave_cliente'],
            'plural' => ['claves_clientes'],
        ];
    }

    private function syncConfig(string $type): ?array
    {
        $method = new ReflectionMethod(EcommerceSyncService::class, 'syncConfig');

        return $method->invoke(new EcommerceSyncService(), $type);
    }
}
