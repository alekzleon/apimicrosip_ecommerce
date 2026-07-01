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

    #[DataProvider('libresClientesTypes')]
    public function test_libres_clientes_sync_config_uses_expected_endpoint_and_payload(string $type): void
    {
        $config = $this->syncConfig($type);

        $this->assertSame('CLIENTE_ID', $config['id_field']);
        $this->assertStringContainsString('CLIENTE_ID', $config['query']);
        $this->assertStringContainsString('UBICACION', $config['query']);
        $this->assertStringContainsString('FROM LIBRES_CLIENTES', $config['query']);
        $this->assertStringContainsString('WHERE CLIENTE_ID = ?', $config['query']);
        $this->assertSame('/api/v1/admin/sync/libres-clientes', $config['endpoint']);
        $this->assertSame('libres_clientes', $config['payload_key']);
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

    /**
     * @return array<string, array{string}>
     */
    public static function libresClientesTypes(): array
    {
        return [
            'camel microsip normalized' => ['librecliente'],
            'singular' => ['libre_cliente'],
            'plural' => ['libres_clientes'],
        ];
    }

    private function syncConfig(string $type): ?array
    {
        $method = new ReflectionMethod(EcommerceSyncService::class, 'syncConfig');

        return $method->invoke(new EcommerceSyncService(), $type);
    }
}
