<?php

namespace Tests\Unit;

use App\Services\SalesDocumentsImportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    public function test_numeric_strings_outside_doctos_ve_det_are_not_normalized(): void
    {
        $record = $this->normalizeFirebirdRecord('DOCTOS_VE', [
            'UNIDADES' => '1.0000',
        ]);

        $this->assertSame('1.0000', $record['UNIDADES']);
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
}
