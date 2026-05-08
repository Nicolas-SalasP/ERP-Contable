<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;

class ComercialFacturaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Facturas SpA', 'regimen_tributario' => '14_D3']);
        $this->usuario = User::create(['nombre' => 'Contador', 'email' => 'conta@f.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);

        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresa->id, 'codigo' => '410101'], ['nombre' => 'Gasto', 'tipo' => 'GASTO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresa->id, 'codigo' => '353350'], ['nombre' => 'IVA', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true]);
        PlanCuenta::firstOrCreate(['empresa_id' => $this->empresa->id, 'codigo' => '352105'], ['nombre' => 'Proveedor', 'tipo' => 'PASIVO', 'imputable' => true, 'activo' => true]);
    }

    public function test_factura_compra_rechaza_montos_matematicamente_inconsistentes()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'P', 'rut' => '1.1.1.1-1', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-MATH',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 999999,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)->assertSee('Inconsistencia tributaria');
    }

    public function test_evita_registrar_factura_de_compra_duplicada_para_el_mismo_proveedor()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'P', 'rut' => '2.2.2.2-2', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $payload = [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-DUP',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 10000,
            'monto_iva' => 1900,
            'monto_bruto' => 11900,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ];

        $this->actingAs($this->usuario)->postJson('/api/facturas', $payload)->assertStatus(201);
        $this->actingAs($this->usuario)->postJson('/api/facturas', $payload)->assertStatus(422)->assertSee('ya se encuentra registrada');
    }

    public function test_registro_de_factura_compra_genera_asiento_contable_automaticamente()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'P', 'rut' => '3.3.3.3-3', 'codigo_interno' => 'P3', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-ASIENTO',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 50000,
            'monto_iva' => 9500,
            'monto_bruto' => 59500,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '410101',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('asientos_contables', ['empresa_id' => $this->empresa->id, 'origen_modulo' => 'compras', 'origen_id' => $response->json('data.id')]);
    }

    public function test_factura_hace_rollback_si_falla_la_centralizacion()
    {
        $proveedor = Proveedor::create(['empresa_id' => $this->empresa->id, 'razon_social' => 'P', 'rut' => '4.4.4.4-4', 'codigo_interno' => 'P4', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $response = $this->actingAs($this->usuario)->postJson('/api/facturas', [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-ROLL',
            'fecha_emision' => now()->format('Y-m-d'),
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_bruto' => 1190,
            'tipo_documento' => 'FACTURA',
            'cuentaDestino' => '999999',
            'cuentaIva' => '353350',
            'cuentaProveedor' => '352105'
        ]);

        $response->assertStatus(422)->assertSee('Verifique que las cuentas');
        $this->assertDatabaseMissing('facturas', ['numero_factura' => 'F-ROLL']);
    }

    public function test_rechaza_reclasificar_factura_que_no_ha_sido_centralizada()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '5.5.5.5-5', 'razon_social' => 'P5', 'codigo_interno' => 'P5', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $facturaSinAsiento = Factura::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-SIN', 'codigo_unico' => 1234567, 'fecha_emision' => now(), 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA', 'comprobante_contable' => null]);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$facturaSinAsiento->id}/reclasificar", [
            'fecha' => now()->format('Y-m-d'),
            'glosa' => 'A',
            'cambios' => ['352130' => '410101']
        ]);
        $response->assertStatus(400)->assertSee('no tiene un asiento contable');
    }


    public function test_anular_factura_reversa_asiento_contable_y_cambia_estado()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '6.6.6.6-6', 'razon_social' => 'P6', 'codigo_interno' => 'P6', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        
        $asiento = new AsientoContable();
        $asiento->empresa_id = $this->empresa->id;
        $asiento->fecha = now()->toDateString();
        $asiento->glosa = 'Asiento Original';
        $asiento->tipo_asiento = 'traspaso';
        $asiento->numero_comprobante = 'T-TEST-1';
        $asiento->estado = 'MAYORIZADO';
        $asiento->save();

        $asiento->detalles()->create(['cuenta_contable' => '410101', 'debe' => 100, 'haber' => 0]);
        $asiento->detalles()->create(['cuenta_contable' => '352105', 'debe' => 0, 'haber' => 100]);

        $factura = new Factura();
        $factura->empresa_id = $this->empresa->id;
        $factura->proveedor_id = $prov->id;
        $factura->numero_factura = 'F-ANULAR';
        $factura->codigo_unico = 998877;
        $factura->fecha_emision = now();
        $factura->monto_bruto = 100;
        $factura->monto_neto = 100;
        $factura->monto_iva = 0;
        $factura->tipo = 'COMPRA';
        $factura->estado = 'REGISTRADA';
        $factura->comprobante_contable = 'T-TEST-1';
        $factura->save();

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/anular", [
            'motivo' => 'Devolucion de mercaderia'
        ]);

        $response->assertStatus(200);
        $this->assertEquals('ANULADA', $factura->fresh()->estado);

        $this->assertDatabaseHas('asientos_contables', [
            'empresa_id' => $this->empresa->id,
            'glosa' => 'Reversa automática por anulación de factura N° F-ANULAR. Motivo: Devolucion de mercaderia'
        ]);
    }

    public function test_rechaza_anular_factura_ya_anulada_previamente()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '7.7.7.7-7', 'razon_social' => 'P7', 'codigo_interno' => 'P7', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-YA-ANULADA', 'codigo_unico' => 112233, 'fecha_emision' => now(), 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA', 'estado' => 'ANULADA']);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/anular", ['motivo' => 'Doble clic malicioso']);

        $response->assertStatus(400)->assertSee('ya fue anulada');
    }

    public function test_rechaza_anular_factura_con_pagos_aplicados_en_tesoreria()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '8.8.8.8-8', 'razon_social' => 'P8', 'codigo_interno' => 'P8', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-PAGADA', 'codigo_unico' => 445566, 'fecha_emision' => now(), 'monto_bruto' => 100, 'monto_neto' => 100, 'monto_iva' => 0, 'tipo' => 'COMPRA', 'estado' => 'PAGADA']);

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/anular", ['motivo' => 'Me arrepenti']);
        $response->assertStatus(400)->assertSee('pagos aplicados en Tesorer');
    }
}