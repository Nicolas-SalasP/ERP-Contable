<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TipoAjusteCritico;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioAjusteCriticoApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        /*
        |--------------------------------------------------------------------------
        | Usuarios demo solo para tests de Inventario
        |--------------------------------------------------------------------------
        |
        | No se agregan al DatabaseSeeder global.
        | No crean roles.
        | No asignan permisos.
        |
        */
        $this->prepararUsuariosInventarioDemo();
    }

    public function test_retorna_401_sin_token_al_listar_tipos(): void
    {
        $response = $this->getJson('/api/inventario/ajustes-criticos/tipos');

        $response->assertStatus(401);
    }

    public function test_contador_puede_listar_tipos_de_ajuste_critico(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
        ]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/ajustes-criticos/tipos');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $response->assertJsonFragment([
            'codigo' => TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL,
        ]);

        $response->assertJsonFragment([
            'codigo' => TipoAjusteCritico::CODIGO_VENCIMIENTO,
        ]);
    }

    public function test_usuario_sin_permiso_ver_no_puede_listar_tipos(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([]);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/ajustes-criticos/tipos');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'No tienes permisos para ejecutar esta operación de inventario.',
            ]);
    }

    public function test_contador_puede_registrar_ajuste_critico_de_deterioro(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_DETERIORO);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 2,
            'motivo' => 'Producto deteriorado en bodega',
            'observacion' => 'Detectado durante control físico de inventario',
            'referencia' => 'DET-API-001',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ajuste crítico registrado correctamente.',
            ]);

        $this->assertDatabaseHas('inventario_ajustes_criticos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'tipo_ajuste_critico_id' => $tipo->id,
            'motivo' => 'Producto deteriorado en bodega',
            'referencia' => 'DET-API-001',
        ]);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(8.0, (float) $stock->stock_actual);
    }

    public function test_contador_puede_registrar_ajuste_critico_positivo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'costo_promedio' => 100,
        ]);

        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 5, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_AJUSTE_CRITICO_POSITIVO);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 4,
            'costo_unitario' => 150,
            'motivo' => 'Corrección positiva autorizada',
            'observacion' => 'Diferencia positiva detectada en conteo físico',
            'referencia' => 'AJ-POS-API-001',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(9.0, (float) $stock->stock_actual);

        $this->assertDatabaseHas('inventario_ajustes_criticos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'tipo_ajuste_critico_id' => $tipo->id,
            'referencia' => 'AJ-POS-API-001',
        ]);
    }

    public function test_auditor_puede_listar_ajustes_criticos_pero_no_registrar(): void
    {
        [$empresa, $usuario] = $this->usuarioAuditorConPermisos([
            'inventario.ajustes_criticos.ver',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_PERDIDA);

        $listado = $this->getJson('/api/inventario/ajustes-criticos');

        $listado->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $registro = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => 'Pérdida detectada',
            'observacion' => 'Auditor no debe registrar ajustes críticos',
        ]);

        $registro->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'No tienes permisos para ejecutar esta operación de inventario.',
            ]);
    }

    public function test_registro_exige_motivo_obligatorio(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_DETERIORO);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => '',
            'observacion' => 'Producto deteriorado',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El motivo es obligatorio para registrar un ajuste crítico.',
            ]);

        $this->assertSame(0, AjusteCriticoInventario::count());
    }

    public function test_registro_exige_observacion_obligatoria(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_PERDIDA);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => 'Pérdida detectada',
            'observacion' => '',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'La observación es obligatoria para registrar un ajuste crítico.',
            ]);

        $this->assertSame(0, AjusteCriticoInventario::count());
    }

    public function test_registro_rechaza_stock_insuficiente(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 2, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_VENCIMIENTO);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 5,
            'motivo' => 'Producto vencido',
            'observacion' => 'Cantidad mayor al stock disponible',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Stock insuficiente para realizar el ajuste negativo.',
            ]);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(2.0, (float) $stock->stock_actual);
        $this->assertSame(0, AjusteCriticoInventario::count());
    }

    public function test_listado_de_ajustes_criticos_respeta_filtros_y_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'sku' => 'API-AJUSTE-FILTRO-001',
        ]);

        $bodega = $this->crearBodega($empresa, [
            'codigo' => 'API-BOD-FILTRO-001',
        ]);

        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_DETERIORO);

        $registro = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => 'Deterioro filtrable',
            'observacion' => 'Debe aparecer en listado filtrado',
        ]);

        $registro->assertStatus(201);

        $this->crearAjusteCriticoDeOtraEmpresa($tipo);

        $response = $this->getJson(
            '/api/inventario/ajustes-criticos'
            . '?producto_id=' . $producto->id
            . '&bodega_id=' . $bodega->id
            . '&tipo_ajuste_critico_id=' . $tipo->id
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 1,
                ],
            ]);

        $response->assertJsonFragment([
            'motivo' => 'Deterioro filtrable',
        ]);

        $response->assertJsonMissing([
            'motivo' => 'Ajuste crítico de empresa ajena',
        ]);
    }

    public function test_puede_ver_detalle_de_ajuste_critico_de_su_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_DETERIORO);

        $registro = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => 'Detalle ajuste crítico',
            'observacion' => 'Debe poder consultarse por ID',
        ]);

        $registro->assertStatus(201);

        $ajusteId = $registro->json('data.id');

        $detalle = $this->getJson('/api/inventario/ajustes-criticos/' . $ajusteId);

        $detalle->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $ajusteId,
                    'empresa_id' => $empresa->id,
                    'motivo' => 'Detalle ajuste crítico',
                ],
            ]);
    }

    public function test_no_puede_ver_detalle_de_ajuste_critico_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
        ]);

        Sanctum::actingAs($usuario);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_DETERIORO);

        $ajusteAjeno = $this->crearAjusteCriticoDeOtraEmpresa($tipo);

        $response = $this->getJson('/api/inventario/ajustes-criticos/' . $ajusteAjeno->id);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'El ajuste crítico solicitado no existe o no pertenece a la empresa.',
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de Inventario
    |--------------------------------------------------------------------------
    */

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Ajuste Critico API Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Ajuste Critico API Test',
            'descripcion' => 'Producto para pruebas Feature/API de ajustes críticos',
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
            'activo' => true,
        ], $overrides));
    }

    private function crearStock(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        float $stockActual,
        float $costoPromedio
    ): StockProducto {
        return StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $stockActual,
            'costo_promedio' => $costoPromedio,
            'valor_total' => $stockActual * $costoPromedio,
        ]);
    }

    private function stock(Empresa $empresa, Producto $producto, Bodega $bodega): StockProducto
    {
        return StockProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();
    }

    private function obtenerUnidadBase(): UnidadMedida
    {
        return UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );
    }

    private function tipo(string $codigo): TipoAjusteCritico
    {
        return TipoAjusteCritico::query()
            ->where('codigo', $codigo)
            ->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | Helper multiempresa
    |--------------------------------------------------------------------------
    |
    | No crea usuarios ni roles.
    | Solo crea datos de inventario de otra empresa para validar aislamiento.
    |
    */

    private function crearAjusteCriticoDeOtraEmpresa(TipoAjusteCritico $tipo): AjusteCriticoInventario
    {
        $empresaAjena = Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Ajena Ajuste Critico API',
        ]);

        $productoAjeno = $this->crearProducto($empresaAjena, [
            'sku' => 'PROD-AJENO-' . strtoupper(substr(uniqid(), -5)),
        ]);

        $bodegaAjena = $this->crearBodega($empresaAjena, [
            'codigo' => 'BOD-AJENA-' . strtoupper(substr(uniqid(), -4)),
        ]);

        $this->crearStock($empresaAjena, $productoAjeno, $bodegaAjena, 10, 100);

        $movimientoAjeno = MovimientoInventario::create([
            'empresa_id' => $empresaAjena->id,
            'producto_id' => $productoAjeno->id,
            'tipo' => MovimientoInventario::TIPO_AJUSTE_NEGATIVO,
            'bodega_origen_id' => $bodegaAjena->id,
            'bodega_destino_id' => null,
            'cantidad' => 1,
            'stock_origen_antes' => 10,
            'stock_origen_despues' => 9,
            'stock_destino_antes' => null,
            'stock_destino_despues' => null,
            'costo_unitario' => 100,
            'costo_total' => 100,
            'referencia' => 'AJENO-API-001',
            'motivo' => MovimientoInventario::MOTIVO_MERMA,
            'observacion' => 'Movimiento ajeno para validar aislamiento multiempresa',
            'created_by' => null,
            'fecha_movimiento' => now(),
        ]);

        return AjusteCriticoInventario::create([
            'empresa_id' => $empresaAjena->id,
            'movimiento_inventario_id' => $movimientoAjeno->id,
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $productoAjeno->id,
            'bodega_id' => $bodegaAjena->id,
            'cantidad' => 1,
            'costo_unitario' => 100,
            'costo_total' => 100,
            'motivo' => 'Ajuste crítico de empresa ajena',
            'observacion' => 'No debe aparecer en reportes de otra empresa',
            'referencia' => 'AJENO-API-001',
            'origen_modulo' => null,
            'origen_id' => null,
            'registrado_por' => null,
        ]);
    }

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}