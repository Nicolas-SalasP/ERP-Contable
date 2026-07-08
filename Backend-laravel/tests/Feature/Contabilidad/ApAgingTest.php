<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ApAgingTest extends TestCase
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
     * Crea una factura de COMPRA con un proveedor.
     *
     * @param int         $empresaId
     * @param float       $montoBruto
     * @param string|null $fechaVencimiento Fecha ISO (Y-m-d) o null para sin vencimiento
     * @param string      $estado          REGISTRADA | PAGADA | ANULADA
     * @param int|null    $proveedorId     Reusar un proveedor existente si se indica
     */
    private function crearFacturaCompra(
        int $empresaId,
        float $montoBruto,
        ?string $fechaVencimiento = null,
        string $estado = 'REGISTRADA',
        ?int $proveedorId = null
    ): Factura {
        if ($proveedorId === null) {
            $proveedor = Proveedor::withoutGlobalScopes()->create([
                'empresa_id'     => $empresaId,
                'rut'            => rand(10000000, 99999999) . '-' . rand(0, 9),
                'razon_social'   => 'Proveedor Test ' . uniqid(),
                'codigo_interno' => 'PROV-' . uniqid(),
                'pais_iso'       => 'CL',
                'moneda_defecto' => 'CLP',
            ]);
            $proveedorId = $proveedor->id;
        }

        $neto = round($montoBruto / 1.19, 2);
        $iva  = round($montoBruto - $neto, 2);

        return Factura::withoutGlobalScopes()->create([
            'empresa_id'        => $empresaId,
            'codigo_unico'      => Factura::generarCodigoUnico(),
            'proveedor_id'      => $proveedorId,
            'numero_factura'    => 'FC-' . uniqid(),
            'tipo'              => 'COMPRA',
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

    public function test_retorna_estructura_json_correcta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresa->id, 100000);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'resumen' => ['corriente', 'd30', 'd60', 'd90', 'd90plus', 'total'],
                    'detalle',
                ],
            ]);
    }

    public function test_factura_sin_vencimiento_va_a_bucket_corriente(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresa->id, 100000, null);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(100000.0, $response->json('data.resumen.corriente'), 0.01);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.d30'), 0.01);
    }

    public function test_factura_no_vencida_va_a_bucket_corriente(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->addDays(15)->toDateString();
        $this->crearFacturaCompra($empresa->id, 200000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(200000.0, $response->json('data.resumen.corriente'), 0.01);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.d30'), 0.01);
    }

    public function test_factura_vencida_20_dias_va_a_bucket_d30(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(20)->toDateString();
        $this->crearFacturaCompra($empresa->id, 150000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(150000.0, $response->json('data.resumen.d30'), 0.01);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.corriente'), 0.01);
    }

    public function test_factura_vencida_45_dias_va_a_bucket_d60(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(45)->toDateString();
        $this->crearFacturaCompra($empresa->id, 300000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(300000.0, $response->json('data.resumen.d60'), 0.01);
    }

    public function test_factura_vencida_75_dias_va_a_bucket_d90(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(75)->toDateString();
        $this->crearFacturaCompra($empresa->id, 500000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(500000.0, $response->json('data.resumen.d90'), 0.01);
    }

    public function test_factura_vencida_mas_de_90_dias_va_a_bucket_d90plus(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $vencimiento = now()->subDays(120)->toDateString();
        $this->crearFacturaCompra($empresa->id, 800000, $vencimiento);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(800000.0, $response->json('data.resumen.d90plus'), 0.01);
    }

    public function test_excluye_facturas_pagadas(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresa->id, 500000, now()->subDays(10)->toDateString(), 'PAGADA');

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    public function test_excluye_facturas_anuladas(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresa->id, 300000, now()->subDays(5)->toDateString(), 'ANULADA');

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    public function test_excluye_facturas_de_tipo_venta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $proveedor = Proveedor::withoutGlobalScopes()->create([
            'empresa_id'     => $empresa->id,
            'rut'            => '76123456-7',
            'razon_social'   => 'Cliente SA',
            'codigo_interno' => 'CLI-001',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        Factura::withoutGlobalScopes()->create([
            'empresa_id'     => $empresa->id,
            'codigo_unico'   => Factura::generarCodigoUnico(),
            'proveedor_id'   => $proveedor->id,
            'numero_factura' => 'FV-001',
            'tipo'           => 'VENTA',
            'tipo_documento' => 'FACTURA',
            'fecha_emision'  => now()->toDateString(),
            'monto_neto'     => 84034.0,
            'monto_iva'      => 15966.0,
            'monto_bruto'    => 100000.0,
            'estado'         => 'REGISTRADA',
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    // ------------------------------------------------------------------
    // Tests: regresión saldo real en facturas ABONADA (pago/NC parcial)
    // ------------------------------------------------------------------

    public function test_factura_abonada_con_anticipo_aplicado_usa_saldo_real_no_monto_bruto(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $factura = $this->crearFacturaCompra($empresa->id, 500000, now()->subDays(5)->toDateString(), 'ABONADA');

        // Anticipo ya aplicado y no revertido contra la factura (AnticipoProveedorService::aplicarAFactura): reduce el saldo pendiente en 200000.
        $anticipoId = DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id'   => $empresa->id,
            'proveedor_id' => $factura->proveedor_id,
            'monto'        => 200000,
            'estado'       => 'APLICADO',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('anticipo_aplicaciones')->insert([
            'empresa_id'  => $empresa->id,
            'anticipo_id' => $anticipoId,
            'factura_id'  => $factura->id,
            'monto'       => 200000,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        // Saldo real: 500000 - 200000 aplicado = 300000. Antes del fix, sumaba el monto bruto completo (500000).
        $this->assertEqualsWithDelta(300000.0, $response->json('data.resumen.total'), 0.01);
    }

    public function test_factura_abonada_con_nota_credito_aplicada_usa_saldo_real_no_monto_bruto(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $factura = $this->crearFacturaCompra($empresa->id, 400000, now()->subDays(5)->toDateString(), 'ABONADA');

        // NC aplicada contra la factura (mismo criterio de ProveedorService::compensarPartidas): reduce el saldo pendiente en 150000.
        Factura::withoutGlobalScopes()->create([
            'empresa_id'             => $empresa->id,
            'codigo_unico'           => Factura::generarCodigoUnico(),
            'proveedor_id'           => $factura->proveedor_id,
            'numero_factura'         => 'NC-' . uniqid(),
            'tipo'                   => 'COMPRA',
            'tipo_documento'         => 'NOTA_CREDITO',
            'fecha_emision'          => now()->toDateString(),
            'monto_neto'             => round(150000 / 1.19, 2),
            'monto_iva'              => 150000 - round(150000 / 1.19, 2),
            'monto_bruto'            => 150000,
            'estado'                 => 'APLICADA',
            'factura_referencia_id'  => $factura->id,
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        // Saldo real: 400000 - 150000 de NC aplicada = 250000.
        $this->assertEqualsWithDelta(250000.0, $response->json('data.resumen.total'), 0.01);
    }

    public function test_factura_abonada_sin_ajustes_aparece_con_monto_bruto(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresa->id, 250000, now()->subDays(5)->toDateString(), 'ABONADA');

        $response = $this->actingAs($usuario)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        // ABONADA sin anticipos/NC registrados: se incluye igual, sin sobreestimar ni excluir.
        $this->assertEqualsWithDelta(250000.0, $response->json('data.resumen.total'), 0.01);
    }

    // ------------------------------------------------------------------
    // Test: aislamiento multitenant
    // ------------------------------------------------------------------

    public function test_empresa_b_no_ve_facturas_de_empresa_a(): void
    {
        // Empresa A tiene una factura de compra pendiente
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        $this->crearFacturaCompra($empresaA->id, 999999, now()->subDays(5)->toDateString());

        // Usuario de empresa B consulta: debe recibir un reporte vacío
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuarioB)
            ->getJson('/api/contabilidad/ap-aging');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(0.0, $response->json('data.resumen.total'), 0.01);
        $this->assertEmpty($response->json('data.detalle'));
    }

    // ------------------------------------------------------------------
    // Test: autenticación
    // ------------------------------------------------------------------

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/contabilidad/ap-aging')
            ->assertStatus(401);
    }
}
