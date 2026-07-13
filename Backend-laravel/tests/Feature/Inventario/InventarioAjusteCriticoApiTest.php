<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\AjusteCriticoInventario;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\InventarioValorizacionCapa;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TipoAjusteCritico;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioAjusteCriticoApiTest extends TestCase
{
    use PreparaInventarioTrait;
    use RefreshDatabase;

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

    public function test_anular_ajuste_critico_negativo_repone_stock_con_movimiento_compensatorio(): void
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
            'cantidad' => 3,
            'motivo' => 'Producto deteriorado',
            'observacion' => 'Detectado en control físico',
        ]);
        $registro->assertStatus(201);
        $ajusteId = $registro->json('data.id');

        $this->assertEquals(7.0, (float) $this->stock($empresa, $producto, $bodega)->stock_actual);

        $anulacion = $this->postJson("/api/inventario/ajustes-criticos/{$ajusteId}/anular", [
            'motivo_anulacion' => 'Cantidad digitada por error, era 0.3 no 3',
        ]);

        $anulacion->assertOk()->assertJson(['success' => true]);

        $this->assertEquals(10.0, (float) $this->stock($empresa, $producto, $bodega)->stock_actual);

        $this->assertDatabaseHas('inventario_ajustes_criticos', [
            'id' => $ajusteId,
            'motivo_anulacion' => 'Cantidad digitada por error, era 0.3 no 3',
        ]);

        $ajuste = AjusteCriticoInventario::find($ajusteId);
        $this->assertNotNull($ajuste->anulado_at);
        $this->assertNotNull($ajuste->movimiento_reversa_id);

        $movimientoReversa = MovimientoInventario::find($ajuste->movimiento_reversa_id);
        $this->assertSame(MovimientoInventario::TIPO_AJUSTE_POSITIVO, $movimientoReversa->tipo);
        $this->assertEquals(3.0, (float) $movimientoReversa->cantidad);
    }

    public function test_anular_ajuste_critico_dos_veces_falla(): void
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

        $registro = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 1,
            'motivo' => 'Pérdida detectada',
            'observacion' => 'Conteo físico',
        ]);
        $ajusteId = $registro->json('data.id');

        $this->postJson("/api/inventario/ajustes-criticos/{$ajusteId}/anular", [
            'motivo_anulacion' => 'Error de digitación',
        ])->assertOk();

        $segundaAnulacion = $this->postJson("/api/inventario/ajustes-criticos/{$ajusteId}/anular", [
            'motivo_anulacion' => 'Intento repetido',
        ]);

        $segundaAnulacion->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Este ajuste crítico ya fue anulado anteriormente.',
            ]);
    }

    /**
     * PHPUnit corre en un único proceso, por lo que no se puede forzar una condición de carrera
     * real entre dos requests concurrentes. Siguiendo el mismo patrón que
     * FifoValorizacionEdgeCasesTest/PmpValorizacionEdgeCasesTest, este test ejercita el
     * lockForUpdate() que InventarioMovimientoService::obtenerOCrearStockBloqueado aplica dentro
     * de cada registrarAjusteCritico(): dos ajustes críticos secuenciales sobre el mismo
     * producto/bodega deben acumularse correctamente, sin que ninguno pierda la actualización del
     * otro.
     */
    public function test_dos_ajustes_criticos_secuenciales_sobre_el_mismo_stock_no_pierden_actualizaciones(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10, 100);

        $tipoPositivo = $this->tipo(TipoAjusteCritico::CODIGO_AJUSTE_CRITICO_POSITIVO);
        $tipoNegativo = $this->tipo(TipoAjusteCritico::CODIGO_PERDIDA);

        $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipoPositivo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 50,
            'motivo' => 'Ajuste concurrente A',
            'observacion' => 'Primera solicitud sobre el mismo stock',
        ])->assertStatus(201);

        $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipoNegativo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 3,
            'motivo' => 'Ajuste concurrente B',
            'observacion' => 'Segunda solicitud sobre el mismo stock',
        ])->assertStatus(201);

        // Si el lock no hubiese serializado las dos lecturas-actualizaciones, alguna de las dos
        // habría partido de un stock_actual desactualizado y el resultado final sería distinto.
        $stock = $this->stock($empresa, $producto, $bodega);
        $this->assertEquals(12.0, (float) $stock->stock_actual); // 10 + 5 - 3
    }

    /**
     * Hallazgo real, documentado también en HALLAZGOS-COLATERALES.md: anularAjusteCritico()
     * revierte el ajuste con un movimiento compensatorio (registrarMovimiento reusa la misma
     * lógica de valorización probada), pero para un producto FIFO ese movimiento compensatorio es
     * una salida que consume capas en orden FIFO estricto (la más antigua primero) — no
     * necesariamente la capa que el propio ajuste creó. Si ya existía stock más antiguo antes del
     * ajuste, la reversa consume ESE stock en su lugar, y la capa que el ajuste crítico anulado
     * creó queda intacta y huérfana, distorsionando el costo del stock remanente pese a que el
     * ajuste "erróneo" fue anulado.
     */
    public function test_anular_ajuste_critico_positivo_fifo_no_revierte_la_capa_que_el_ajuste_creo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'metodo_valorizacion' => 'FIFO',
        ]);
        $bodega = $this->crearBodega($empresa);

        // Stock inicial real: 10 unidades a $50 (capa más antigua).
        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
            'costo_unitario' => 50,
            'motivo' => MovimientoInventario::MOTIVO_INGRESO_MANUAL,
        ])->assertCreated();

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_AJUSTE_CRITICO_POSITIVO);

        // Ajuste crítico positivo: 5 unidades a $200 (capa nueva y más cara).
        $registro = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 5,
            'costo_unitario' => 200,
            'motivo' => 'Ajuste positivo a revisar',
            'observacion' => 'Se anulará para verificar la reversión de capas FIFO',
        ]);
        $registro->assertStatus(201);
        $ajusteId = $registro->json('data.id');

        $stockTrasAjuste = $this->stock($empresa, $producto, $bodega);
        $this->assertEquals(15.0, (float) $stockTrasAjuste->stock_actual);

        $anulacion = $this->postJson("/api/inventario/ajustes-criticos/{$ajusteId}/anular", [
            'motivo_anulacion' => 'Cantidad/costo incorrectos, se corrige por otra vía',
        ]);
        $anulacion->assertOk();

        // La cantidad total vuelve a la original (10): esto sí es correcto.
        $stockTrasAnulacion = $this->stock($empresa, $producto, $bodega);
        $this->assertEquals(10.0, (float) $stockTrasAnulacion->stock_actual);

        // Pero la composición de capas quedó distorsionada: la reversa (salida FIFO) consumió la
        // capa MÁS ANTIGUA (la entrada real de $50), no la capa que el propio ajuste creó ($200).
        $capas = InventarioValorizacionCapa::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->orderBy('fecha_entrada')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $capas);

        $capaEntradaOriginal = $capas[0];
        $this->assertEquals(50.0, (float) $capaEntradaOriginal->costo_unitario);
        $this->assertEquals(5.0, (float) $capaEntradaOriginal->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capaEntradaOriginal->estado);

        $capaDelAjuste = $capas[1];
        $this->assertEquals(200.0, (float) $capaDelAjuste->costo_unitario);
        // La capa que creó el ajuste anulado queda intacta y huérfana: nadie la consumió.
        $this->assertEquals(5.0, (float) $capaDelAjuste->cantidad_disponible);
        $this->assertEquals(InventarioValorizacionCapa::ESTADO_ABIERTA, $capaDelAjuste->estado);

        // Consecuencia: el costo del stock remanente queda en (5*50 + 5*200)/10 = $125 unitario
        // promedio, muy por encima de los $50 originales, pese a que el ajuste "erróneo" que
        // introdujo el costo de $200 fue anulado.
        $stockTrasAnulacion->refresh();
        $this->assertEquals(1250.0, (float) $stockTrasAnulacion->valor_total);
    }

    /*
    |--------------------------------------------------------------------------
    | Ajuste crítico + lotes
    |--------------------------------------------------------------------------
    |
    | Test dirigido: verifica si un ajuste crítico de salida (ej. merma) sobre
    | un producto que maneja lotes exige lote_id, dado que el payload de
    | registrarAjusteCritico() nunca declaraba esa clave hacia registrarMovimiento().
    */
    public function test_ajuste_critico_de_merma_sin_lote_id_falla_si_producto_maneja_lotes(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);
        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);
        $this->crearStockConLote($empresa, $producto, $bodega, $lote, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 2,
            'motivo' => 'Merma detectada en bodega',
            'observacion' => 'Detectado durante control físico de inventario',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseMissing('inventario_ajustes_criticos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
        ]);
    }

    public function test_ajuste_critico_de_merma_con_lote_id_explicito_funciona(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);
        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);
        $this->crearStockConLote($empresa, $producto, $bodega, $lote, 10, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_MERMA_OPERACIONAL);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 2,
            'motivo' => 'Merma detectada en bodega',
            'observacion' => 'Detectado durante control físico de inventario',
            'lote_id' => $lote->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $stock = $this->stock($empresa, $producto, $bodega);
        $this->assertEquals(8.0, (float) $stock->stock_actual);

        $this->assertDatabaseHas('inventario_ajustes_criticos', [
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'tipo_ajuste_critico_id' => $tipo->id,
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

    public function test_registro_permite_ajuste_negativo_de_exactamente_el_stock_disponible(): void
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
            'cantidad' => 2,
            'motivo' => 'Producto vencido',
            'observacion' => 'Cantidad exactamente igual al stock disponible',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $stock = $this->stock($empresa, $producto, $bodega);

        $this->assertEquals(0.0, (float) $stock->stock_actual);
    }

    /**
     * Un producto FIFO no acepta que su capa se valorice con el promedio ponderado del stock (ver
     * fix de FifoValorizacionStrategy::calcularEntrada() y HALLAZGOS-COLATERALES.md): un ajuste
     * crítico positivo hereda esa exigencia porque reutiliza registrarMovimiento() sin declarar
     * costo_unitario si el usuario no lo informa. Se confirma aquí explícitamente (no estaba
     * cubierto) que el ajuste crítico se rechaza y no queda ningún registro a medio crear.
     */
    public function test_ajuste_critico_positivo_sin_costo_unitario_falla_para_producto_fifo(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos([
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
        ]);

        Sanctum::actingAs($usuario);

        $producto = $this->crearProducto($empresa, [
            'metodo_valorizacion' => 'FIFO',
        ]);

        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 5, 100);

        $tipo = $this->tipo(TipoAjusteCritico::CODIGO_AJUSTE_CRITICO_POSITIVO);

        $response = $this->postJson('/api/inventario/ajustes-criticos', [
            'tipo_ajuste_critico_id' => $tipo->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => 4,
            'motivo' => 'Corrección positiva sin costo informado',
            'observacion' => 'Producto FIFO, usuario no informó costo_unitario',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $stock = $this->stock($empresa, $producto, $bodega);
        $this->assertEquals(5.0, (float) $stock->stock_actual);

        $this->assertSame(0, AjusteCriticoInventario::where('producto_id', $producto->id)->count());
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
            .'?producto_id='.$producto->id
            .'&bodega_id='.$bodega->id
            .'&tipo_ajuste_critico_id='.$tipo->id
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

        $detalle = $this->getJson('/api/inventario/ajustes-criticos/'.$ajusteId);

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

        $response = $this->getJson('/api/inventario/ajustes-criticos/'.$ajusteAjeno->id);

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
            'codigo' => 'BOD-'.strtoupper(substr(uniqid(), -6)),
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
            'sku' => 'PROD-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Ajuste Critico API Test',
            'descripcion' => 'Producto para pruebas Feature/API de ajustes críticos',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
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

    private function crearLote(Empresa $empresa, Producto $producto, array $overrides = []): LoteInventario
    {
        return LoteInventario::create(array_merge([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-'.strtoupper(substr(uniqid(), -8)),
            'fecha_fabricacion' => now()->subMonth()->toDateString(),
            'fecha_vencimiento' => now()->addMonth()->toDateString(),
            'activo' => true,
            'estado_operativo' => LoteInventario::ESTADO_DISPONIBLE,
        ], $overrides));
    }

    private function crearStockConLote(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote,
        float $cantidad,
        float $costoUnitario
    ): void {
        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $cantidad,
            'costo_promedio' => $costoUnitario,
            'valor_total' => $cantidad * $costoUnitario,
        ]);

        StockLoteInventario::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'lote_id' => $lote->id,
            'stock_actual' => $cantidad,
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
            'sku' => 'PROD-AJENO-'.strtoupper(substr(uniqid(), -5)),
        ]);

        $bodegaAjena = $this->crearBodega($empresaAjena, [
            'codigo' => 'BOD-AJENA-'.strtoupper(substr(uniqid(), -4)),
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
        return (string) random_int(70000000, 99999999).'-'.random_int(0, 9);
    }
}
