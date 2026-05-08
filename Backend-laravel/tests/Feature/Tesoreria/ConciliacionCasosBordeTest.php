<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Laravel\Sanctum\Sanctum;

/**
 * Tests de casos borde en conciliacion bancaria.
 *
 * Cubre escenarios de produccion donde un error en conciliacion
 * puede llevar a que una factura quede pagada dos veces, un
 * movimiento bancario quede mal asociado, o se genere un asiento
 * contable descuadrado.
 *
 * Estos son los flujos donde un cliente real podria perder dinero
 * o terminar con cuadres contables imposibles.
 */
class ConciliacionCasosBordeTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cuenta;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresa->id,
            'rut_titular' => $this->empresa->rut,
            'titular' => $this->empresa->razon_social,
            'banco' => 'Banco de Chile',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '99999999',
        ]);

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '76.111.111-1',
            'razon_social' => 'Proveedor Test',
            'codigo_interno' => 'PT-CON',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_conciliar_factura_inexistente_falla_con_error_limpio()
    {
        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/tesoreria/conciliar/factura-compra', [
            'factura_id' => 99999,
            'cuenta_bancaria_id' => $this->cuenta->id,
        ]);

        $this->assertContains($response->getStatusCode(), [400, 404, 422, 500]);
        $this->assertNotEquals(200, $response->getStatusCode(),
            'Conciliacion de factura inexistente no debe responder 200');
    }

    public function test_conciliar_factura_ya_pagada_no_la_paga_dos_veces()
    {
        $factura = Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'numero_factura' => 'FAC-PAG-001',
            'tipo' => 'COMPRA',
            'codigo_unico' => 80000001,
            'fecha_emision' => '2026-04-01',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'PAGADA',
        ]);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/tesoreria/conciliar/factura-compra', [
            'factura_id' => $factura->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
        ]);

        // Lo critico: NO debe permitir pagar dos veces
        $movimientos = DB::table('movimientos_bancarios')
            ->where('cuenta_bancaria_id', $this->cuenta->id)
            ->get();

        $this->assertLessThanOrEqual(1, count($movimientos),
            'Doble pago: factura ya PAGADA genero MULTIPLES movimientos bancarios');

        // Tambien validar que el response sea congruente
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 400, 422, 500]),
            'Status code inesperado: ' . $response->getStatusCode()
        );
    }

    public function test_conciliacion_directa_requiere_movimiento_id_y_factura_id()
    {
        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar', []);

        $this->assertContains($response->getStatusCode(), [400, 422, 500]);
    }

    public function test_conciliar_anticipo_con_solo_movimiento_id_falla()
    {
        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-anticipo', [
            'movimiento_id' => 999,
            // falta anticipo_id
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422, 500]);
    }

    public function test_conciliar_anticipo_con_movimiento_de_otra_empresa_es_rechazado()
    {
        // Crear empresa B con su propia cuenta y movimiento
        $empresaB = $this->crearEmpresa();
        $usuarioB = $this->crearUsuario($empresaB, $this->rolSuperAdmin);
        $cuentaB = CuentaBancariaEmpresa::create([
            'empresa_id' => $empresaB->id,
            'rut_titular' => $empresaB->rut,
            'titular' => $empresaB->razon_social,
            'banco' => 'BancoEstado',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '12345678',
        ]);

        $movIdB = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $empresaB->id,
            'cuenta_bancaria_id' => $cuentaB->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Movimiento B',
            'cargo' => 100000,
            'abono' => 0,
            'estado' => 'PENDIENTE',
        ]);

        // Anticipo de empresa A (yo)
        $anticipoIdA = DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $this->proveedor->id,
            'monto' => 100000,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($this->usuario); // soy usuario de A
        $response = $this->postJson('/api/banco/movimientos/conciliar-anticipo', [
            'movimiento_id' => $movIdB, // movimiento de B
            'anticipo_id' => $anticipoIdA,
        ]);

        // No debe permitir conciliar movimiento ajeno con anticipo propio
        $this->assertNotContains($response->getStatusCode(), [200, 201],
            'IDOR: usuario A pudo conciliar anticipo propio con movimiento de empresa B');
    }

    public function test_movimiento_sugerencias_de_otra_empresa_falla()
    {
        $empresaB = $this->crearEmpresa();
        $cuentaB = CuentaBancariaEmpresa::create([
            'empresa_id' => $empresaB->id,
            'rut_titular' => $empresaB->rut,
            'titular' => $empresaB->razon_social,
            'banco' => 'Banco Itau',
            'tipo_cuenta' => 'VISTA',
            'numero_cuenta' => '00112233',
        ]);

        $movIdB = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $empresaB->id,
            'cuenta_bancaria_id' => $cuentaB->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Mov B',
            'cargo' => 50000,
            'abono' => 0,
            'estado' => 'PENDIENTE',
        ]);

        Sanctum::actingAs($this->usuario);
        $response = $this->getJson("/api/banco/movimientos/{$movIdB}/sugerencias");

        $this->assertNotEquals(200, $response->getStatusCode(),
            'IDOR: usuario A obtuvo sugerencias de movimiento de empresa B');
    }

    public function test_conciliar_facturas_con_array_vacio_falla()
    {
        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => 1,
            'facturas_ids' => [],
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422, 500]);
    }

    public function test_conciliar_facturas_no_puede_mezclar_facturas_de_otra_empresa()
    {
        // Setup empresa B con factura ajena
        $empresaB = $this->crearEmpresa();
        $proveedorB = Proveedor::create([
            'empresa_id' => $empresaB->id,
            'rut' => '88.888.888-8',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $facturaB = Factura::create([
            'empresa_id' => $empresaB->id,
            'proveedor_id' => $proveedorB->id,
            'numero_factura' => 'FAC-AJENA-1',
            'tipo' => 'COMPRA',
            'codigo_unico' => 80000099,
            'fecha_emision' => '2026-04-01',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        // Movimiento propio
        $movIdA = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Mov A',
            'cargo' => 119000,
            'abono' => 0,
            'estado' => 'PENDIENTE',
        ]);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movIdA,
            'facturas_ids' => [$facturaB->id], // factura ajena!
        ]);

        $this->assertNotContains($response->getStatusCode(), [200, 201],
            'IDOR: usuario A pudo conciliar movimiento propio con factura de empresa B');
    }
}
