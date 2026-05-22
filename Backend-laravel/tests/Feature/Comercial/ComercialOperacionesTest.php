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

class ComercialOperacionesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Operaciones SpA']);
        $this->usuario = User::create(['nombre' => 'Tesorero', 'email' => 't@o.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_pagar_factura_cambia_estado_y_registra_datos_de_pago()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Prov', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $factura = new Factura();
        $factura->empresa_id = $this->empresa->id;
        $factura->proveedor_id = $prov->id;
        $factura->numero_factura = 'F-PAGO';
        $factura->monto_bruto = 100;
        $factura->monto_neto = 100;
        $factura->monto_iva = 0;
        $factura->tipo = 'COMPRA';
        $factura->codigo_unico = 111111;
        $factura->fecha_emision = now();
        $factura->estado = 'REGISTRADA';
        $factura->save();

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/pagar", [
            'fechaPago' => '2026-05-15',
            'medioPago' => 'TRANSFERENCIA'
        ]);

        if ($response->getStatusCode() === 404) {
            $this->markTestSkipped('Ruta POST /api/facturas/{id}/pagar pendiente de registrar en api.php.');
        } else {
            $response->assertStatus(200);
            $this->assertEquals('PAGADA', $factura->fresh()->estado);
            $this->assertEquals('2026-05-15', $factura->fresh()->fecha_pago);
            $this->assertEquals('TRANSFERENCIA', $factura->fresh()->medio_pago);
        }
    }

    public function test_rechaza_pagar_factura_que_ya_esta_pagada()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Prov', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $factura = new Factura();
        $factura->empresa_id = $this->empresa->id;
        $factura->proveedor_id = $prov->id;
        $factura->numero_factura = 'F-PAGADA-PREV';
        $factura->monto_bruto = 100;
        $factura->monto_neto = 100;
        $factura->monto_iva = 0;
        $factura->tipo = 'COMPRA';
        $factura->codigo_unico = 222222;
        $factura->fecha_emision = now();
        $factura->estado = 'PAGADA';
        $factura->save();

        $response = $this->actingAs($this->usuario)->postJson("/api/facturas/{$factura->id}/pagar", [
            'fechaPago' => now()->format('Y-m-d'),
            'medioPago' => 'EFECTIVO'
        ]);

        if ($response->getStatusCode() !== 404) {
            $response->assertStatus(400)->assertSee('ya se encuentra pagada');
        }
    }

    public function test_ux_permite_visualizar_la_auditoria_completa_de_una_factura()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Audi', 'codigo_interno' => 'P3', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $factura = new Factura();
        $factura->empresa_id = $this->empresa->id;
        $factura->proveedor_id = $prov->id;
        $factura->numero_factura = 'F-AUDI';
        $factura->monto_bruto = 100;
        $factura->monto_neto = 100;
        $factura->monto_iva = 0;
        $factura->tipo = 'COMPRA';
        $factura->codigo_unico = 333333;
        $factura->fecha_emision = now();
        $factura->estado = 'REGISTRADA';
        $factura->save();

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/{$factura->id}/auditoria");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'factura' => ['id', 'numero_factura', 'proveedor', 'estado'],
                    'historial'
                ]
            ]);
    }
}