<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;

class ProyectoActivoEliminacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create(['rut' => '1.1.1-1', 'razon_social' => 'Del SPA']);
        $this->usuario = User::create([
            'nombre' => 'Del',
            'email' => 'd@erp.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    private function crearProyecto(array $overrides = []): ProyectoActivo
    {
        return ProyectoActivo::create(array_merge([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Proyecto X',
            'vida_util_meses' => 60,
            'valor_total_original' => 0,
            'estado' => 'EN_CONSTRUCCION',
        ], $overrides));
    }

    private function crearProveedor(): Proveedor
    {
        return Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '76.111.222-3',
            'razon_social' => 'Prov',
            'codigo_interno' => 'P-' . uniqid(),
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    private function crearFactura(int $proveedorId, ?int $proyectoId, float $neto): Factura
    {
        return Factura::create([
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedorId,
            'proyecto_activo_id' => $proyectoId,
            'tipo' => 'COMPRA',
            'tipo_documento' => 'FACTURA',
            'numero_factura' => 'F-' . uniqid(),
            'codigo_unico' => (int) (time() . rand(100000, 999999)),
            'fecha_emision' => '2026-04-15',
            'monto_neto' => $neto,
            'monto_iva' => round($neto * 0.19, 2),
            'monto_bruto' => round($neto * 1.19, 2),
            'estado' => 'REGISTRADA',
        ]);
    }

    public function test_puede_eliminar_proyecto_en_construccion_vacio()
    {
        $proyecto = $this->crearProyecto();

        $response = $this->actingAs($this->usuario)
            ->deleteJson("/api/activos/proyectos/{$proyecto->id_proyecto}");

        $response->assertStatus(200);

        // Verificar que se elimino
        $this->assertNull(ProyectoActivo::find($proyecto->id_proyecto));
    }

    public function test_rechaza_eliminar_proyecto_con_facturas_vinculadas()
    {
        $proyecto = $this->crearProyecto(['valor_total_original' => 100000]);
        $proveedor = $this->crearProveedor();
        $this->crearFactura($proveedor->id, $proyecto->id_proyecto, 100000);

        $response = $this->actingAs($this->usuario)
            ->deleteJson("/api/activos/proyectos/{$proyecto->id_proyecto}");

        $response->assertStatus(400);
        $this->assertStringContainsString('factura', strtolower($response->json('message')));

        // Verificar que NO se elimino
        $this->assertNotNull(ProyectoActivo::find($proyecto->id_proyecto));
    }

    public function test_desvincular_factura_resta_costo_al_proyecto()
    {
        $proyecto = $this->crearProyecto(['valor_total_original' => 250000]);
        $proveedor = $this->crearProveedor();
        $factura = $this->crearFactura($proveedor->id, $proyecto->id_proyecto, 250000);

        $response = $this->actingAs($this->usuario)
            ->deleteJson("/api/activos/proyectos/{$proyecto->id_proyecto}/facturas/{$factura->id}");

        $response->assertStatus(200);

        // Factura desvinculada
        $this->assertNull($factura->fresh()->proyecto_activo_id);

        // Proyecto con valor restado
        $this->assertEquals(0, (float) $proyecto->fresh()->valor_total_original);
    }
}
