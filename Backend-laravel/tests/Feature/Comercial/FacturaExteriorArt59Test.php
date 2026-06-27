<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Art. 59 LIR — retención en remesas al exterior.
 *
 * Al pagar servicios a proveedores extranjeros, la empresa chilena debe
 * retener un % y enterarlo al SII vía Formulario 50.
 */
class FacturaExteriorArt59Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private FacturaService $service;
    private Empresa $empresa;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(FacturaService::class);

        $this->empresa = Empresa::create([
            'rut' => '76.999.001-5',
            'razon_social' => 'Art59 Test SpA',
        ]);

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'codigo_interno' => 'EXT-ART59',
            'razon_social' => 'Global SaaS Ltd.',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'USD',
        ]);

        foreach ([
            ['640000', 'Servicios Digitales Exterior', 'GASTO'],
            ['353350', 'IVA Crédito Fiscal', 'ACTIVO'],
            ['352105', 'Proveedores Extranjeros', 'PASIVO'],
            ['252200', 'Retención Art. 59 LIR por Pagar', 'PASIVO'],
        ] as [$codigo, $nombre, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    private function payload(string $numero, array $extras = []): array
    {
        return array_merge([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => $numero,
            'fecha_emision' => '2026-06-15',
            'es_documento_exterior' => true,
            'moneda' => 'USD',
            'tipo_cambio' => 1000.00,
            'monto_bruto_origen' => 100.00,
            'monto_bruto' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 0,
            'tipo_documento' => 'FACTURA_EXTERIOR',
            'cuentaDestino' => '640000',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105',
        ], $extras);
    }

    public function test_sin_tipo_gasto_art59_no_calcula_retencion(): void
    {
        $factura = $this->service->registrarFacturaCompra($this->payload('ART59-001'));

        $this->assertNull($factura->tipo_gasto_art59);
        $this->assertNull($factura->retencion_art59);
    }

    public function test_intereses_calcula_4_porciento(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-002', ['tipo_gasto_art59' => 'intereses', 'cuentaRetencion' => '252200'])
        );

        $this->assertEquals('intereses', $factura->tipo_gasto_art59);
        // 100.000 × 4% = 4.000
        $this->assertEquals(4000.00, (float) $factura->retencion_art59);
    }

    public function test_regalias_calcula_30_porciento(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-003', ['tipo_gasto_art59' => 'regalias', 'cuentaRetencion' => '252200'])
        );

        $this->assertEquals(30000.00, (float) $factura->retencion_art59);
    }

    public function test_servicios_tecnicos_calcula_15_porciento(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-004', ['tipo_gasto_art59' => 'servicios_tecnicos', 'cuentaRetencion' => '252200'])
        );

        $this->assertEquals(15000.00, (float) $factura->retencion_art59);
    }

    public function test_otros_calcula_35_porciento(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-005', ['tipo_gasto_art59' => 'otros', 'cuentaRetencion' => '252200'])
        );

        $this->assertEquals(35000.00, (float) $factura->retencion_art59);
    }

    public function test_asiento_con_retencion_genera_linea_retención_y_ajusta_cxp(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-006', ['tipo_gasto_art59' => 'regalias', 'cuentaRetencion' => '252200'])
        );

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        $this->assertNotNull($asiento);
        $this->assertCount(3, $asiento->detalles, 'Gasto + CxP ajustada + Retención Art. 59');

        $cuentas = $asiento->detalles->keyBy('cuenta_contable');

        // Gasto = bruto completo
        $this->assertEquals(100000.0, (float) $cuentas['640000']->debe);

        // CxP proveedor = bruto - retención = 100.000 - 30.000 = 70.000
        $this->assertEquals(70000.0, (float) $cuentas['352105']->haber);

        // Retención Art. 59 = 30% de 100.000
        $this->assertEquals(30000.0, (float) $cuentas['252200']->haber);
    }

    public function test_asiento_cuadra_debe_igual_haber(): void
    {
        $factura = $this->service->registrarFacturaCompra(
            $this->payload('ART59-007', ['tipo_gasto_art59' => 'otros', 'cuentaRetencion' => '252200'])
        );

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        $totalDebe = $asiento->detalles->sum('debe');
        $totalHaber = $asiento->detalles->sum('haber');

        $this->assertEquals($totalDebe, $totalHaber, 'Asiento debe cuadrar (partida doble)');
    }

    public function test_tipo_gasto_invalido_lanza_excepcion(): void
    {
        $this->expectException(\App\Domains\Comercial\Exceptions\ComercialException::class);

        $this->service->registrarFacturaCompra(
            $this->payload('ART59-008', ['tipo_gasto_art59' => 'dividendos'])
        );
    }

    public function test_cuenta_retencion_inexistente_lanza_excepcion(): void
    {
        $this->expectException(\App\Domains\Comercial\Exceptions\ComercialException::class);

        $this->service->registrarFacturaCompra(
            $this->payload('ART59-009', ['tipo_gasto_art59' => 'regalias', 'cuentaRetencion' => '999999'])
        );
    }

    public function test_retencion_no_aplica_a_documentos_nacionales(): void
    {
        // Un documento nacional no debe calcular retención aunque venga tipo_gasto_art59
        $datos = [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'ART59-010',
            'fecha_emision' => '2026-06-15',
            'es_documento_exterior' => false,
            'moneda' => 'CLP',
            'monto_bruto' => 119000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '640000',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105',
            'tipo_gasto_art59' => 'regalias',
        ];

        $factura = $this->service->registrarFacturaCompra($datos);

        $this->assertNull($factura->retencion_art59, 'Nacional no debe tener retención Art. 59');
    }
}
