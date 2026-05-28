<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioUbicacion;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\StockUbicacionInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioFase13UbicacionesApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crea_y_lista_ubicaciones_por_bodega(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-A']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/ubicaciones', [
            'bodega_id' => $bodega->id,
            'codigo' => 'PAS-A-01',
            'nombre' => 'Pasillo A posición 01',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
            'pasillo' => 'A',
            'estante' => '01',
            'nivel' => '01',
            'posicion' => '01',
            'capacidad_maxima' => 100,
            'activo' => true,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.codigo', 'PAS-A-01')
            ->assertJsonPath('data.bodega_id', $bodega->id);

        $this->getJson('/api/inventario/ubicaciones?bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.codigo', 'PAS-A-01');
    }

    public function test_rechaza_ubicacion_padre_de_otra_bodega(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $bodegaA = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-B1']);
        $bodegaB = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-B2']);

        $padre = $this->crearUbicacion($empresa, $bodegaA, ['codigo' => 'ZONA-A', 'tipo' => InventarioUbicacion::TIPO_ZONA]);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/ubicaciones', [
            'bodega_id' => $bodegaB->id,
            'ubicacion_padre_id' => $padre->id,
            'codigo' => 'HIJO-INVALIDO',
            'nombre' => 'Hijo en bodega incorrecta',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_entrada_con_ubicacion_crea_stock_disponible_y_kardex_con_coordenada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-C']);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'DISP-01']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_destino_id' => $ubicacion->id,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'cantidad' => 12,
            'costo_unitario' => 1500,
            'referencia' => 'ENT-F13-UBI',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ubicacion_destino_id', $ubicacion->id);

        $this->assertDatabaseHas('inventario_stock_ubicaciones', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'ubicacion_id' => $ubicacion->id,
            'stock_actual' => 12,
            'stock_reservado' => 0,
        ]);

        $this->getJson('/api/inventario/kardex?ubicacion_id=' . $ubicacion->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.referencia', 'ENT-F13-UBI');
    }

    public function test_reserva_con_ubicacion_compromete_y_liberacion_expirada_devuelve_bucket(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-D']);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'DISP-02']);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaUbicacion($producto, $bodega, $ubicacion, 10, 'ENT-F13-RES');

        $this->postJson('/api/inventario/reservas', [
            'referencia' => 'RES-F13-UBI',
            'fecha_expiracion' => now()->subDay()->toDateString(),
            'detalles' => [[
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'ubicacion_id' => $ubicacion->id,
                'estado_stock' => StockUbicacionInventario::ESTADO_DISPONIBLE,
                'cantidad' => 4,
            ]],
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $stockReservado = StockUbicacionInventario::query()->where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(4.0, (float) $stockReservado->stock_reservado);

        $this->getJson('/api/inventario/reservas')->assertOk();

        $stockLiberado = StockUbicacionInventario::query()->where('ubicacion_id', $ubicacion->id)->firstOrFail();
        $this->assertEquals(0.0, (float) $stockLiberado->stock_reservado);
        $this->assertEquals(10.0, (float) $stockLiberado->stock_actual);
    }

    public function test_rechaza_reserva_sobre_stock_en_cuarentena(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-E']);
        $ubicacion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'CUAR-01']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_destino_id' => $ubicacion->id,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_CUARENTENA,
            'cantidad' => 5,
            'costo_unitario' => 900,
            'referencia' => 'ENT-F13-CUAR',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $this->postJson('/api/inventario/reservas', [
            'referencia' => 'RES-F13-CUAR',
            'detalles' => [[
                'producto_id' => $producto->id,
                'bodega_id' => $bodega->id,
                'ubicacion_id' => $ubicacion->id,
                'cantidad' => 1,
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_putaway_mueve_stock_desde_recepcion_a_disponible(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa, ['codigo' => 'BOD-F13-F']);
        $recepcion = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'REC-01']);
        $disponible = $this->crearUbicacion($empresa, $bodega, ['codigo' => 'RACK-01']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_destino_id' => $recepcion->id,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_EN_RECEPCION,
            'cantidad' => 8,
            'costo_unitario' => 1000,
            'referencia' => 'ENT-F13-REC',
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $this->postJson('/api/inventario/putaway/confirmar', [
            'producto_id' => $producto->id,
            'bodega_origen_id' => $bodega->id,
            'bodega_destino_id' => $bodega->id,
            'ubicacion_origen_id' => $recepcion->id,
            'ubicacion_destino_id' => $disponible->id,
            'estado_stock_origen' => StockUbicacionInventario::ESTADO_EN_RECEPCION,
            'estado_stock_destino' => StockUbicacionInventario::ESTADO_DISPONIBLE,
            'cantidad' => 3,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $stockRecepcion = StockUbicacionInventario::query()->where('ubicacion_id', $recepcion->id)->firstOrFail();
        $stockDisponible = StockUbicacionInventario::query()->where('ubicacion_id', $disponible->id)->firstOrFail();

        $this->assertEquals(5.0, (float) $stockRecepcion->stock_actual);
        $this->assertEquals(5.0, (float) $stockRecepcion->stock_en_transito);
        $this->assertEquals(3.0, (float) $stockDisponible->stock_actual);
        $this->assertEquals(3.0, $stockDisponible->stockDisponible());
    }

    public function test_ubicaciones_respetan_multiempresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosFase13());
        $otraEmpresa = $this->crearEmpresa();
        $bodegaAjena = $this->crearBodega($otraEmpresa, ['codigo' => 'BOD-F13-AJENA']);
        $ubicacionAjena = $this->crearUbicacion($otraEmpresa, $bodegaAjena, ['codigo' => 'AJENA-01']);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/ubicaciones/' . $ubicacionAjena->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/inventario/ubicaciones', [
            'bodega_id' => $bodegaAjena->id,
            'codigo' => 'NO-CRUZAR',
            'nombre' => 'No debe cruzar empresa',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_ubicaciones', [
            'empresa_id' => $empresa->id,
            'codigo' => 'NO-CRUZAR',
        ]);
    }

    private function permisosFase13(): array
    {
        return array_values(array_unique(array_merge($this->permisosInventarioOperador(), [
            'inventario.ubicaciones.ver',
            'inventario.ubicaciones.crear',
            'inventario.ubicaciones.editar',
            'inventario.stock_ubicaciones.ver',
            'inventario.stock_ubicaciones.mover',
            'inventario.putaway.ejecutar',
            'inventario.disponibilidad.ver',
            'inventario.kardex.ver',
        ])));
    }

    private function registrarEntradaUbicacion(
        Producto $producto,
        Bodega $bodega,
        InventarioUbicacion $ubicacion,
        float $cantidad,
        string $referencia
    ): void {
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
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario Fase 13 ' . uniqid(),
        ]);
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Fase 13',
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
            'nombre' => 'Ubicación Fase 13',
            'tipo' => InventarioUbicacion::TIPO_UBICACION,
            'activo' => true,
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'F13-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Fase 13',
            'descripcion' => 'Producto para ubicaciones y putaway',
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

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}
