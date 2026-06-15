<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Mockery;
use Tests\TestCase;

class MicrosipPurchasedItemsTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_purchased_items_queries_firebird_with_client_and_last_three_months(): void
    {
        config(['services.internal_api.key' => 'test-key']);
        Date::setTestNow('2026-06-13 10:00:00');

        $connection = Mockery::mock();
        $connection
            ->shouldReceive('select')
            ->once()
            ->with(
                Mockery::on(fn (string $query): bool => str_contains($query, 'FROM DOCTOS_VE_DET dvd')
                    && str_contains($query, 'dv.CLIENTE_ID = ?')
                    && str_contains($query, "dv.TIPO_DOCTO = 'P'")),
                [7532041, '2026-03-13', '2026-06-13']
            )
            ->andReturn([
                (object) [
                    'DOCTO_VE_ID' => 123,
                    'CLAVE_ARTICULO' => 'ABC',
                    'ARTICULO_ID' => 456,
                ],
            ]);

        DB::shouldReceive('connection')
            ->once()
            ->with('firebird')
            ->andReturn($connection);

        $response = $this
            ->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/microsip/clientes/7532041/articulos-comprados');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('cliente_id', 7532041)
            ->assertJsonPath('fecha_inicio', '2026-03-13')
            ->assertJsonPath('fecha_fin', '2026-06-13')
            ->assertJsonPath('data.0.DOCTO_VE_ID', 123)
            ->assertJsonPath('data.0.CLAVE_ARTICULO', 'ABC')
            ->assertJsonPath('data.0.ARTICULO_ID', 456);
    }

    public function test_purchased_items_rejects_invalid_client_id(): void
    {
        config(['services.internal_api.key' => 'test-key']);

        $response = $this
            ->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/microsip/clientes/abc/articulos-comprados');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'cliente_id invalido');
    }
}
