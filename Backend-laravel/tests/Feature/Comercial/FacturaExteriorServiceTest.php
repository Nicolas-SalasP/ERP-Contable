<?php

namespace Tests\Feature\Comercial;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Contabilidad\Models\PlanCuenta;

class FacturaExteriorServiceTest extends TestCase
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
            'rut' => '76.123.456-7',
            'razon_social' => 'Empresa Exterior Test SpA',
        ]);

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'codigo_interno' => 'EXT-001',
            'razon_social' => 'Anthropic Inc.',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'USD',
        ]);

        foreach ([
            ['640000', 'Servicios Digitales Exterior', 'GASTO'],
            ['353350', 'IVA Crédito Fiscal', 'ACTIVO'],
            ['352105', 'Proveedores', 'PASIVO'],
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

    private function payloadBase(string $numero): array
    {
        return [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => $numero,
            'fecha_emision' => '2026-06-10',
            'es_documento_exterior' => true,
            'moneda' => 'USD',
            'tipo_cambio' => 950.00,
            'monto_bruto_origen' => 23.80,
            'monto_bruto' => 22610,
            'monto_neto' => 22610,
            'monto_iva' => 0,
            'tipo_documento' => 'FACTURA_EXTERIOR',
            'cuentaDestino' => '640000',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105',
        ];
    }

    public function test_registra_factura_exterior_con_iva_cero(): void
    {
        $factura = $this->service->registrarFacturaCompra($this->payloadBase('RX1GPPWH-0001'));

        $this->assertEquals(0, (float) $factura->monto_iva);
        $this->assertEquals('USD', $factura->moneda);
        $this->assertEquals(23.80, (float) $factura->monto_bruto_origen);
        $this->assertTrue($factura->es_documento_exterior);
    }

    public function test_asiento_de_factura_exterior_no_genera_linea_iva_credito(): void
    {
        $factura = $this->service->registrarFacturaCompra($this->payloadBase('RX1GPPWH-0002'));

        $asiento = AsientoContable::with('detalles')
            ->where('empresa_id', $this->empresa->id)
            ->where('numero_comprobante', $factura->comprobante_contable)
            ->first();

        $this->assertNotNull($asiento);

        $cuentasUsadas = $asiento->detalles->pluck('cuenta_contable')->toArray();
        $this->assertNotContains('353350', $cuentasUsadas, 'No debe existir línea de IVA crédito fiscal');
        $this->assertContains('640000', $cuentasUsadas, 'Debe existir línea de gasto');
        $this->assertContains('352105', $cuentasUsadas, 'Debe existir línea de CxP proveedor');
        $this->assertCount(2, $asiento->detalles);
    }

    public function test_monto_gasto_es_el_total_convertido_a_clp(): void
    {
        $factura = $this->service->registrarFacturaCompra($this->payloadBase('RX1GPPWH-0003'));

        // 23.80 USD × 950 = 22.610 CLP
        $this->assertEquals(22610, (float) $factura->monto_bruto);
        $this->assertEquals(22610, (float) $factura->monto_neto);
    }

    public function test_factura_exterior_requiere_tipo_cambio(): void
    {
        $this->expectException(\App\Domains\Comercial\Exceptions\ComercialException::class);

        $payload = $this->payloadBase('EXT-SIN-TC');
        $payload['tipo_cambio'] = 0;

        $this->service->registrarFacturaCompra($payload);
    }

    public function test_factura_exterior_requiere_moneda_extranjera(): void
    {
        $this->expectException(\App\Domains\Comercial\Exceptions\ComercialException::class);

        $payload = $this->payloadBase('EXT-SIN-MONEDA');
        $payload['moneda'] = 'CLP';

        $this->service->registrarFacturaCompra($payload);
    }
}
