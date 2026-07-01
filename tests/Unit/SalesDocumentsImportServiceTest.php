<?php

namespace Tests\Unit;

use App\Services\SalesDocumentsImportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class SalesDocumentsImportServiceTest extends TestCase
{
    public function test_doctos_ve_det_unit_strings_are_normalized_before_firebird_insert(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE_DET', [
            'UNIDADES' => '1.0000',
            'UNIDADES_COMPROM' => '2.5000',
            'CLAVE_ARTICULO' => '000123',
        ]);

        $this->assertSame(1.0, $record['UNIDADES']);
        $this->assertSame(2.5, $record['UNIDADES_COMPROM']);
        $this->assertSame('000123', $record['CLAVE_ARTICULO']);
    }

    public function test_doctos_ve_det_integer_units_are_normalized_before_firebird_insert(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE_DET', [
            'UNIDADES' => 1,
        ]);

        $this->assertSame(1.0, $record['UNIDADES']);
    }

    public function test_doctos_ve_det_price_strings_are_normalized_before_firebird_insert(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE_DET', [
            'PRECIO_UNITARIO' => '40.00',
            'PRECIO_TOTAL_NETO' => '80.0000',
            'PRECIO_ARTICULO_ID' => '123',
            'CLAVE_ARTICULO' => '000123',
        ]);

        $this->assertSame(40.0, $record['PRECIO_UNITARIO']);
        $this->assertSame(80.0, $record['PRECIO_TOTAL_NETO']);
        $this->assertSame('123', $record['PRECIO_ARTICULO_ID']);
        $this->assertSame('000123', $record['CLAVE_ARTICULO']);
    }

    public function test_doctos_ve_total_strings_are_normalized_before_firebird_insert(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE', [
            'TOTAL' => '3359.00',
            'IMPORTE_NETO' => '2895.69',
            'TOTAL_IMPUESTOS' => '463.31',
            'DOCTO_VE_ID' => '123',
            'FOLIO' => '000123',
        ]);

        $this->assertSame(3359.0, $record['TOTAL']);
        $this->assertSame(2895.69, $record['IMPORTE_NETO']);
        $this->assertSame(463.31, $record['TOTAL_IMPUESTOS']);
        $this->assertSame('123', $record['DOCTO_VE_ID']);
        $this->assertSame('000123', $record['FOLIO']);
    }

    public function test_doctos_ve_total_with_thousands_separator_is_normalized_before_firebird_insert(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE', [
            'TOTAL' => '3,359.00',
        ]);

        $this->assertSame(3359.0, $record['TOTAL']);
    }

    public function test_doctos_ve_total_must_be_a_valid_decimal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Valor decimal invalido para DOCTOS_VE.TOTAL.');

        $this->normalizeFirebirdRecord('DOCTOS_VE', [
            'TOTAL' => 'no valido',
        ]);
    }

    public function test_non_decimal_numeric_strings_outside_doctos_ve_det_are_not_normalized(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE', [
            'UNIDADES' => '1.0000',
            'TIPO_CAMBIO' => '1.0000',
        ]);

        $this->assertSame('1.0000', $record['UNIDADES']);
        $this->assertSame(1.0, $record['TIPO_CAMBIO']);
    }

    public function test_committed_units_alias_is_mapped_to_firebird_column(): void
    {
        $record = $this->applyFirebirdColumnAliases('DOCTOS_VE_DET', [
            'UNIDADES_COMPROMETIDAS' => '1.0000',
        ]);

        $this->assertSame('1.0000', $record['UNIDADES_COMPROM']);
        $this->assertArrayNotHasKey('UNIDADES_COMPROMETIDAS', $record);
    }

    public function test_committed_units_are_always_zero_for_sales_document_details(): void
    {
        $missing = $this->applyFirebirdDetailDefaults('DOCTOS_VE_DET', [
            'UNIDADES' => '1.0000',
        ]);

        $withValue = $this->applyFirebirdDetailDefaults('DOCTOS_VE_DET', [
            'UNIDADES' => '2.0000',
            'UNIDADES_COMPROM' => '2.0000',
        ]);

        $this->assertSame(0.0, $missing['UNIDADES_COMPROM']);
        $this->assertSame(0.0, $withValue['UNIDADES_COMPROM']);
    }

    public function test_libres_ped_ve_record_is_built_from_sales_document_fiscal_fields(): void
    {
        $record = $this->buildLibresPedVeRecord([
            'uso_cfdi' => '18649598',
            'metodo_pago_sat' => '18649600',
            'forma_pago' => '18649602',
        ], [], 123);

        $this->assertSame(123, $record['DOCTO_VE_ID']);
        $this->assertSame('No', $record['RECOGEN']);
        $this->assertSame(18649598, $record['USO_DE_CFDI']);
        $this->assertSame(18649600, $record['METODO_DE_PAGO']);
        $this->assertSame(18649602, $record['FORMA_DE_PAGO']);
        $this->assertSame('S', $record['PICKTOLIGTH']);
        $this->assertSame('N', $record['PREPAGO_AUTORIZADO']);
        $this->assertSame(31491119, $record['ESTATUS_INICIAL_DE_CARGO']);
        $this->assertSame('N', $record['SAR']);
    }

    public function test_libres_ped_ve_record_accepts_fiscal_fields_from_doctos_ve_header(): void
    {
        $record = $this->buildLibresPedVeRecord([], [
            'USO_CFDI' => '18649597',
            'METODO_PAGO_SAT' => '18649601',
            'FORMA_PAGO' => '18649605',
        ], 123);

        $this->assertSame(18649597, $record['USO_DE_CFDI']);
        $this->assertSame(18649601, $record['METODO_DE_PAGO']);
        $this->assertSame(18649605, $record['FORMA_DE_PAGO']);
    }

    public function test_libres_ped_ve_source_fields_are_removed_from_doctos_ve_header_insert(): void
    {
        $record = $this->withoutArrayKeys([
            'FOLIO' => 'P123',
            'USO_CFDI' => '18649597',
            'METODO_PAGO_SAT' => '18649601',
            'FORMA_PAGO' => '18649605',
        ], ['uso_cfdi', 'metodo_pago_sat', 'forma_pago']);

        $this->assertSame(['FOLIO' => 'P123'], $record);
    }

    public function test_libres_ped_ve_record_uses_defaults_when_fiscal_fields_are_missing(): void
    {
        $record = $this->buildLibresPedVeRecord([], [], 123);

        $this->assertSame(18649597, $record['USO_DE_CFDI']);
        $this->assertSame(18649600, $record['METODO_DE_PAGO']);
        $this->assertSame(18649605, $record['FORMA_DE_PAGO']);
    }

    public function test_libres_ped_ve_record_uses_defaults_when_fiscal_fields_are_invalid(): void
    {
        $record = $this->buildLibresPedVeRecord([
            'uso_cfdi' => 'no valido',
            'metodo_pago_sat' => '',
            'forma_pago' => null,
        ], [], 123);

        $this->assertSame(18649597, $record['USO_DE_CFDI']);
        $this->assertSame(18649600, $record['METODO_DE_PAGO']);
        $this->assertSame(18649605, $record['FORMA_DE_PAGO']);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function normalizeFirebirdRecord(string $table, array $record): array
    {
        $method = new ReflectionMethod(SalesDocumentsImportService::class, 'normalizeFirebirdRecord');

        return $method->invoke(new SalesDocumentsImportService(), $table, $record);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function applyFirebirdColumnAliases(string $table, array $record): array
    {
        $method = new ReflectionMethod(SalesDocumentsImportService::class, 'applyFirebirdColumnAliases');

        return $method->invoke(new SalesDocumentsImportService(), $table, $record);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function applyFirebirdDetailDefaults(string $table, array $record): array
    {
        $method = new ReflectionMethod(SalesDocumentsImportService::class, 'applyFirebirdDetailDefaults');

        return $method->invoke(new SalesDocumentsImportService(), $table, $record);
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $header
     * @return array<string, mixed>
     */
    private function buildLibresPedVeRecord(array $document, array $header, mixed $doctoVeId): array
    {
        $method = new ReflectionMethod(SalesDocumentsImportService::class, 'buildLibresPedVeRecord');

        return $method->invoke(new SalesDocumentsImportService(), $document, $header, $doctoVeId);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string> $names
     * @return array<string, mixed>
     */
    private function withoutArrayKeys(array $record, array $names): array
    {
        $method = new ReflectionMethod(SalesDocumentsImportService::class, 'withoutArrayKeys');

        return $method->invoke(new SalesDocumentsImportService(), $record, $names);
    }
}
