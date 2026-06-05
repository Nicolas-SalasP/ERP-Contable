<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

/**
 * Fase 26 — caracterización de los 6 métodos de InventarioController sin cobertura
 * previa, para que la extracción del controller (H7) no sea ciega:
 *   stockUbicaciones, moverStockUbicacion, updateUbicacion,
 *   showReglaReposicion, updateReglaReposicion, destroyReglaReposicion.
 *
 * El foco es el contrato HTTP (status + forma de respuesta), que es lo que un
 * refactor de controller puede romper sin tocar los servicios.
 */
class InventarioFase26CoberturaHuecosTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararUsuariosInventarioDemo();
    }

    // ---- stockUbicaciones (GET /stock-ubicaciones) ----

    public function test_stock_ubicaciones_lista_el_stock_por_ubicacion(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'STK-01']);

        Sanctum::actingAs($usuario);
        $this->registrarEntrada($producto, $bodega, $ubicacion, 7, 'ENT-F26-STK');

        $this->getJson('/api/inventario/stock-ubicaciones?producto_id=' . $producto->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.producto_id', $producto->id)
            ->assertJsonPath('data.0.ubicacion_id', $ubicacion->id);
    }

    // ---- moverStockUbicacion (POST /stock-ubicaciones/mover) ----

    public function test_mover_stock_entre_ubicaciones_traslada_la_cantidad(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $origen = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'MOV-ORIG']);
        $destino = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'MOV-DEST']);

        Sanctum::actingAs($usuario);
        $this->registrarEntrada($producto, $bodega, $origen, 10, 'ENT-F26-MOV');

        $this->postJson('/api/inventario/stock-ubicaciones/mover', [
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_origen_id' => $origen->id,
            'ubicacion_destino_id' => $destino->id,
            'estado_stock_origen' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'cantidad' => 4,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $stockDestino = StockUbicacionInventario::query()->where('ubicacion_id', $destino->id)->firstOrFail();
        $this->assertEquals(4.0, (float) $stockDestino->stock_actual);
    }

    public function test_mover_stock_valida_campos_requeridos(): void
    {
        [, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/stock-ubicaciones/mover', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['producto_id', 'cantidad']]);
    }

    // ---- updateUbicacion (PUT /ubicaciones/{id}) ----

    public function test_actualiza_ubicacion(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        $bodega = $this->crearBodega($empresa);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'UPD-01', 'nombre' => 'Antes']);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/inventario/ubicaciones/' . $ubicacion->id, ['nombre' => 'Después'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nombre', 'Después');
    }

    public function test_actualizar_ubicacion_de_otra_empresa_falla(): void
    {
        [, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        $otraEmpresa = $this->crearEmpresa();
        $bodegaAjena = $this->crearBodega($otraEmpresa);
        $ubicacionAjena = $this->crearUbicacion($otraEmpresa, $bodegaAjena, ['codigo' => 'AJENA-UPD']);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/inventario/ubicaciones/' . $ubicacionAjena->id, ['nombre' => 'Hackeo'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ---- reglas de reposición: show / update / destroy ----

    public function test_show_actualiza_y_elimina_regla_reposicion(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosInventarioOperador());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $reglaId = $this->postJson('/api/inventario/reglas-reposicion', [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 5,
            'stock_objetivo' => 20,
            'punto_reorden' => 8,
        ])->assertCreated()->json('data.id');

        // show
        $this->getJson('/api/inventario/reglas-reposicion/' . $reglaId)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $reglaId);

        // update
        $this->putJson('/api/inventario/reglas-reposicion/' . $reglaId, [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_minimo' => 9,
            'stock_objetivo' => 30,
        ])->assertOk()
            ->assertJsonPath('success', true)
            // La API devuelve los decimales como string ('9.0000'): contrato real fijado.
            ->assertJsonPath('data.stock_minimo', '9.0000');

        // destroy
        $this->deleteJson('/api/inventario/reglas-reposicion/' . $reglaId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('inventario_reglas_reposicion', ['id' => $reglaId]);
    }

    // ---- helpers (espejo de InventarioFase13UbicacionesApiTest) ----

    private function registrarEntrada(Producto $producto, Bodega $bodega, InventarioUbicacion $ubicacion, float $cantidad, string $referencia): void
    {
        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_destino_id' => $ubicacion->id,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'cantidad' => $cantidad,
            'costo_unitario' => 1000,
            'referencia' => $referencia,
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();
    }

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => (string) random_int(70000000, 99999999) . '-' . random_int(0, 9),
            'razon_social' => 'Empresa Inventario Fase 26 ' . uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Fase 26',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearUbicacion(Empresa $empresa, Bodega $bodega, array $overrides = []): InventarioUbicacion
    {
        return InventarioUbicacion::create(array_merge([
            'empresa_id' => $empresa->id,
            'bodega_id' => $bodega->id,
            'codigo' => 'UBI-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Ubicación Fase 26',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
            'activo' => true,
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'F26-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Fase 26',
            'descripcion' => 'Producto para cobertura de huecos',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'maneja_lotes' => false,
            'activo' => true,
        ], $overrides));
    }
}
