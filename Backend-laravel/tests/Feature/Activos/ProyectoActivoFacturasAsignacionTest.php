<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ProyectoActivoFacturasAsignacionTest extends TestCase
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

        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Facturas SPA', 'regimen_tributario' => '14_A']);
        $this->usuario = User::create(['nombre' => 'Cajero', 'email' => 'caja@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $rol->id, 'estado_suscripcion_id' => 1]);
    }

    public function test_factura_pagada_es_valida_para_capitalizar()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'Planta', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'P1', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        $factura = Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 11, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-PAGADA', 'monto_neto' => 50000, 'monto_iva' => 0, 'monto_bruto' => 50000, 'tipo' => 'COMPRA', 'estado' => 'PAGADA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->postJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas", [
            'factura_id' => $factura->id,
            'monto' => 50000
        ]);

        $response->assertStatus(200);
        $this->assertEquals(50000, $proyecto->fresh()->valor_total_original);
    }

    public function test_endpoint_facturas_disponibles_retorna_razon_social_del_proveedor()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Constructora Legal SPA', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 1, 'proveedor_id' => $prov->id, 'numero_factura' => 'F-1', 'monto_neto' => 100, 'monto_bruto' => 100, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos/facturas-disponibles');

        $response->assertStatus(200);
        $response->assertJsonFragment(['proveedor' => 'Constructora Legal SPA']);
    }
}