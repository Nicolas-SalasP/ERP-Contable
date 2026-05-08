<?php

namespace Tests\Feature\Comercial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ComercialConsultasTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        EstadoSuscripcion::create(['id' => 1, 'nombre' => 'Activa']);
        $rol = Rol::create(['id' => 1, 'nombre' => 'Admin', 'jerarquia' => 100]);
        Pais::create(['iso' => 'CL', 'nombre' => 'Chile', 'moneda_defecto' => 'CLP', 'etiqueta_id' => 'RUT', 'activo' => true]);

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Consultas SpA']);
        $this->usuario = User::create(['nombre' => 'Admin', 'email' => 'admin@cons.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_ux_historial_permite_buscar_y_paginar_facturas()
    {
        $prov1 = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '1.1.1.1-1', 'razon_social' => 'Apple Chile', 'codigo_interno' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        $prov2 = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '2.2.2.2-2', 'razon_social' => 'Microsoft Chile', 'codigo_interno' => 'P2', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $f1 = new Factura();
        $f1->empresa_id = $this->empresa->id;
        $f1->proveedor_id = $prov1->id;
        $f1->numero_factura = 'F-MAC';
        $f1->monto_bruto = 100;
        $f1->monto_neto = 84;
        $f1->monto_iva = 16;
        $f1->tipo = 'COMPRA';
        $f1->codigo_unico = 101010;
        $f1->fecha_emision = now();
        $f1->save();
        $f2 = new Factura();
        $f2->empresa_id = $this->empresa->id;
        $f2->proveedor_id = $prov2->id;
        $f2->numero_factura = 'F-WIN';
        $f2->monto_bruto = 100;
        $f2->monto_neto = 84;
        $f2->monto_iva = 16;
        $f2->tipo = 'COMPRA';
        $f2->codigo_unico = 202020;
        $f2->fecha_emision = now();
        $f2->save();

        $response = $this->actingAs($this->usuario)->getJson('/api/facturas/historial?search=Apple');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('F-MAC', $response->json('data.0.numero_factura'));
        $response->assertJsonStructure(['data', 'pagination' => ['total', 'totalPages', 'page']]);
    }

    public function test_ux_validador_asincrono_detecta_facturas_duplicadas_en_tiempo_real()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'rut' => '3.3.3.3-3', 'razon_social' => 'Prov Test', 'codigo_interno' => 'P3', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $f = new Factura();
        $f->empresa_id = $this->empresa->id;
        $f->proveedor_id = $prov->id;
        $f->numero_factura = 'F-EXISTE';
        $f->monto_bruto = 100;
        $f->monto_neto = 100;
        $f->monto_iva = 0;
        $f->tipo = 'COMPRA';
        $f->codigo_unico = 303030;
        $f->fecha_emision = now();
        $f->save();

        $response = $this->actingAs($this->usuario)->getJson("/api/facturas/check?proveedorId={$prov->id}&numeroFactura=F-EXISTE");

        $response->assertStatus(200)->assertJsonPath('exists', true);
    }

    public function test_ux_maneja_correctamente_la_solicitud_de_documentos_inexistentes()
    {
        $response = $this->actingAs($this->usuario)->getJson('/api/facturas/999999');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message']);
    }
}