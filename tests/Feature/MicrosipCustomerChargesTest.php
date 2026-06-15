<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Mockery;
use Tests\TestCase;

class MicrosipCustomerChargesTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_customer_charges_executes_firebird_procedure_with_expected_parameters(): void
    {
        config(['services.internal_api.key' => 'test-key']);
        Date::setTestNow('2026-06-13 10:00:00');

        $connection = Mockery::mock();
        $connection
            ->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn (string $query): bool => str_contains($query, 'EXECUTE PROCEDURE CARGOS_CLIENTE (?, ?, ?, ?, ?)')
                    && ! str_contains($query, 'FROM CARGOS_CLIENTE')),
                [7532041, '2026-06-13', '12-31-9999', 'S', 'S']
            )
            ->andReturn([
                (object) [
                    'DOCTO_CC_ID' => 100,
                    'FECHA_VENCIMIENTO' => '2026-07-01',
                    'CONCEPTO_CC_ID' => 12,
                    'FOLIO' => "A\xD1123",
                    'ATRASO' => 0,
                    'IMPORTE_CARGO' => '150.50',
                    'SALDO_CARGO' => '50.25',
                ],
            ]);

        DB::shouldReceive('connection')
            ->once()
            ->with('firebird')
            ->andReturn($connection);

        $response = $this
            ->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/microsip/clientes/7532041/cargos');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cliente_id', 7532041)
            ->assertJsonPath('fecha', '2026-06-13')
            ->assertJsonPath('fecha_final', '12-31-9999')
            ->assertJsonPath('data.0.DOCTO_CC_ID', 100)
            ->assertJsonPath('data.0.FOLIO', 'AÑ123')
            ->assertJsonPath('data.0.SALDO_CARGO', '50.25');
    }

    public function test_customer_charges_rejects_invalid_client_id(): void
    {
        config(['services.internal_api.key' => 'test-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/microsip/clientes/abc/cargos');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'cliente_id invalido');
    }
}
