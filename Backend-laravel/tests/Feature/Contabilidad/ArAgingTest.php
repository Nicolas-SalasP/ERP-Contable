<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ArAgingTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ------------------------------------------------------------------
    // Helpers de setup
    // ------------------------------------------------------------------

    /**
     * Crea una factura de VENTA con un cliente.
     *
     * @param int         $empresaId
     * @param float       $montoBruto
     * @param string|null $fechaVencimiento Fecha ISO (Y-m-d) o null para sin vencimiento
     * @param string      $estado          REGISTRADA | PAGADA | ANULADA
     * @param int|null    $clienteId       Reusar un cliente existente si se indica
     */
    private function crearFacturaVenta(
        int $empresaId,
        float $montoBruto,
        ?string $fechaVencimiento = null,
        string $estado = 'REGISTRADA',
        ?int $clienteId = null
    ): Factura {
        if ($clienteId === null) {
            $cliente = Cliente::withoutGlobalScopes()->create([
                'empresa_id'   => $empresaId,
                'rut'          => rand(10000000, 99999999) . '-' . rand(0, 9),
                'razon_social' => 'Cliente Test ' . uniqid(),
                'estado'       => 'ACTIVO',
            ]);
            $clienteId = $cliente->id;
        }

        $neto = round($montoBruto / 1.19, 2);
        $iva  = round($montoBruto - $neto, 2);

        return Factura::withoutGlobalScopes()->create([
            'empresa_id'        => $empresaId,
            'codigo_unico'      => Factura::generarCodigoUnico(),
            'cliente_id'        => $clienteId,
            'numero_factura'    => 'FV-' . uniqid(),
            'tipo'              => 'VENTA',
            'tipo_documento'    => 'FACTURA',
            'fecha_emision'     => now()->toDateString(),
            'fecha_vencimiento' => $fechaVencimiento,
            'monto_neto'        => $neto,
            'monto_iva'         => $iva,
            'monto_bruto'       => $montoBruto,
            'estado'            => $estado,
        ]);
    }

    // ------------------------------------------------------------------
    // Tests: estructura y contenido
    // ------------------------------------------------------------------

    public function test_retorna_estructura_correcta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaVenta($empresa->id, 100000);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'resumen' => ['corriente', 'd30', 'd60', 'd90', 'd90plus', 'total'],
                    'detalle',
                ],
            ]);
    }

    public function test_resumen_tiene_5_buckets(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaVenta($empresa->id, 50000);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);

        $resumen = $response->json('data.resumen');
        $this->assertArrayHasKey('corriente', $resumen);
        $this->assertArrayHasKey('d30', $resumen);
        $this->assertArrayHasKey('d60', $resumen);
        $this->assertArrayHasKey('d90', $resumen);
        $this->assertArrayHasKey('d90plus', $resumen);
        $this->assertArrayHasKey('total', $resumen);
    }

    public function test_excluye_facturas_pagadas_y_anuladas(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaVenta($empresa->id, 500000, now()->subDays(10)->toDateString(), 'PAGADA');
        $this->crearFacturaVenta($empresa->id, 300000, now()->subDays(5)->toDateString(), 'ANULADA');

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    public function test_solo_incluye_tipo_venta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Crear una factura de COMPRA — no debe aparecer en AR Aging
        Factura::withoutGlobalScopes()->create([
            'empresa_id'     => $empresa->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'numero_factura' => 'FC-AR-001',
            'tipo'           => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 84034.0,
            'monto_iva'      => 15966.0,
            'monto_bruto'    => 100000.0,
            'estado'         => 'REGISTRADA',
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    public function test_sin_autenticacion_retorna_401(): void
    {
        $this->getJson('/api/contabilidad/ar-aging')
            ->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Tests: clasificación de buckets
    // ------------------------------------------------------------------

    public function test_factura_sin_vencimiento_va_a_bucket_corriente(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaVenta($empresa->id, 100000, null);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(100000.0, $response->json('data.resumen.corriente'), 0.01);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.d30'), 0.01);
    }

    public function test_factura_vencida_20_dias_va_a_bucket_d30(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(20)->toDateString();
        $this->crearFacturaVenta($empresa->id, 150000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(150000.0, $response->json('data.resumen.d30'), 0.01);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.corriente'), 0.01);
    }

    public function test_factura_vencida_mas_de_90_dias_va_a_bucket_d90plus(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(120)->toDateString();
        $this->crearFacturaVenta($empresa->id, 800000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(800000.0, $response->json('data.resumen.d90plus'), 0.01);
    }

    // ------------------------------------------------------------------
    // Test: aislamiento multitenant
    // ------------------------------------------------------------------

    public function test_empresa_b_no_ve_facturas_de_empresa_a(): void
    {
        // Empresa A tiene una factura de venta pendiente
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        $this->crearFacturaVenta($empresaA->id, 999999, now()->subDays(5)->toDateString());

        // Usuario de empresa B consulta: debe recibir un reporte vacío
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuarioB)
            ->getJson('/api/contabilidad/ar-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }
}
