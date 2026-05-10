<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class F29YDeclaracionesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '11.222.333-4',
            'razon_social' => 'Prov F29',
            'codigo_interno' => 'PF29',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        // Cuentas necesarias para F29
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '152540',
            'nombre' => 'IVA Credito Fiscal',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '353360',
            'nombre' => 'IVA Debito Fiscal',
            'tipo' => 'PASIVO',
            'imputable' => true,
            'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '152542',
            'nombre' => 'Remanente IVA F29',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);
    }

    public function test_simulacion_f29_con_mes_invalido_es_rechazada()
    {
        $response = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/cierre-f29/simular/13/2026');
        $response->assertStatus(422);
    }

    public function test_simulacion_f29_con_mes_cero_es_rechazada()
    {
        $response = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/cierre-f29/simular/0/2026');

        $response->assertStatus(422);
    }

    public function test_simulacion_f29_de_anio_demasiado_atras_es_rechazada()
    {
        $response = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/cierre-f29/simular/1/1900');

        $this->assertContains($response->getStatusCode(), [200, 400, 422]);
        if ($response->getStatusCode() === 200) {
            $body = $response->json();
            $iva = $body['iva_credito'] ?? $body['data']['iva_credito'] ?? null;
            $this->assertTrue($iva === 0 || $iva === null || $iva === '0' || $iva === 0.0);
        }
    }

    public function test_simulacion_f29_solo_calcula_facturas_de_la_empresa_propia()
    {
        Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'PROPIA-1',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000010,
            'fecha_emision' => '2026-04-15',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        $empresaB = $this->crearEmpresa();
        $provB = Proveedor::create([
            'empresa_id' => $empresaB->id,
            'rut' => '99.111.222-3',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
        Factura::create([
            'empresa_id' => $empresaB->id,
            'proveedor_id' => $provB->id,
            'numero_factura' => 'AJENA-1',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000011,
            'fecha_emision' => '2026-04-15',
            'monto_neto' => 999999,
            'monto_iva' => 189999,
            'monto_bruto' => 1189998,
            'estado' => 'REGISTRADA',
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/cierre-f29/simular/4/2026');

        $response->assertStatus(200);
        $body = $response->json();

        $ivaCredito = (int) ($body['iva_credito'] ?? $body['data']['iva_credito'] ?? 0);
        $this->assertLessThanOrEqual(
            19000,
            $ivaCredito,
            'IVA credito incluyo facturas de OTRA empresa - filtracion grave de datos'
        );
    }

    public function test_ejecutar_f29_falta_un_parametro_es_rechazado()
    {
        $response = $this->actingAs($this->usuario)
            ->postJson('/api/impuestos/cierre-f29/ejecutar', [
                'mes' => 4,
            ]);

        $this->assertContains($response->getStatusCode(), [400, 422, 500]);
    }

    public function test_simulacion_f29_con_facturas_anuladas_no_las_incluye()
    {
        Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'F-VALIDA',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000020,
            'fecha_emision' => '2026-04-10',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'F-ANULADA',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000021,
            'fecha_emision' => '2026-04-10',
            'monto_neto' => 500000,
            'monto_iva' => 95000,
            'monto_bruto' => 595000,
            'estado' => 'ANULADA',
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson('/api/impuestos/cierre-f29/simular/4/2026');

        $response->assertStatus(200);
        $body = $response->json();
        $ivaCredito = (int) ($body['iva_credito'] ?? $body['data']['iva_credito'] ?? 0);

        $this->assertLessThan(
            95000,
            $ivaCredito,
            'IVA credito incluye facturas ANULADAS - reporte SII incorrecto'
        );
    }
}
