<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ProyectoActivoConsultasTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '77.777.777-7', 'razon_social' => 'Empresa Consultas', 'regimen_tributario' => '14_D3']);
        $this->usuario = User::create(['nombre' => 'Consultor', 'email' => 'cons@erp.cl', 'password' => bcrypt('123'), 'empresa_id' => $this->empresa->id, 'rol_id' => $this->rolSuperAdmin->id, 'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id]);
    }

    public function test_analisis_proyecto_retorna_estructura_completa_incluso_sin_facturas()
    {
        $proyecto = ProyectoActivo::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Análisis Vacio',
            'estado' => 'EN_CONSTRUCCION',
            'valor_total_original' => 0,
            'vida_util_meses' => 60
        ]);

        $response = $this->actingAs($this->usuario)->getJson("/api/activos/proyectos/{$proyecto->id_proyecto}/analisis");
        $response->assertStatus(200);
        $response->assertJsonFragment(['nombre' => 'Análisis Vacio']);
        $response->assertJsonFragment(['valor_total_original' => 0]);
        $response->assertJsonFragment(['depreciacion_acumulada' => 0]);
    }

    public function test_facturas_disponibles_ignora_facturas_ya_asignadas_a_otro_proyecto()
    {
        $proyecto = ProyectoActivo::create(['empresa_id' => $this->empresa->id, 'nombre' => 'P1', 'estado' => 'EN_CONSTRUCCION', 'valor_total_original' => 0, 'vida_util_meses' => 60]);
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);

        Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 11, 'proveedor_id' => $prov->id, 'numero_factura' => 'LIBRE', 'monto_neto' => 100, 'monto_iva' => 0, 'monto_bruto' => 100, 'tipo' => 'COMPRA', 'fecha_emision' => now()]);
        Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 22, 'proveedor_id' => $prov->id, 'numero_factura' => 'ASIGNADA', 'monto_neto' => 100, 'monto_iva' => 0, 'monto_bruto' => 100, 'tipo' => 'COMPRA', 'fecha_emision' => now(), 'proyecto_activo_id' => $proyecto->id_proyecto]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos/facturas-disponibles');

        $response->assertStatus(200);
        $response->assertJsonFragment(['numero_factura' => 'LIBRE']);
        $response->assertJsonMissing(['numero_factura' => 'ASIGNADA']);
    }

    public function test_facturas_disponibles_ignora_facturas_anuladas()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 99, 'proveedor_id' => $prov->id, 'numero_factura' => 'ANULADA', 'monto_neto' => 100, 'monto_iva' => 0, 'monto_bruto' => 100, 'tipo' => 'COMPRA', 'estado' => 'ANULADA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos/facturas-disponibles');

        $response->assertStatus(200);
        $response->assertJsonMissing(['numero_factura' => 'ANULADA']);
    }

    public function test_facturas_disponibles_ignora_facturas_de_venta()
    {
        $prov = Proveedor::create(['empresa_id' => $this->empresa->id, 'codigo_interno' => 'P1', 'razon_social' => 'Prov', 'pais_iso' => 'CL', 'moneda_defecto' => 'CLP']);
        Factura::create(['empresa_id' => $this->empresa->id, 'codigo_unico' => 88, 'proveedor_id' => $prov->id, 'numero_factura' => 'VENTA', 'monto_neto' => 100, 'monto_iva' => 0, 'monto_bruto' => 100, 'tipo' => 'FACTURA', 'estado' => 'EMITIDA', 'fecha_emision' => now()]);

        $response = $this->actingAs($this->usuario)->getJson('/api/activos/proyectos/facturas-disponibles');

        $response->assertStatus(200);
        $response->assertJsonMissing(['numero_factura' => 'VENTA']);
    }
}