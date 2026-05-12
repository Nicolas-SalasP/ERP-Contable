<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\TomaFisicaDetalleInventario;
use App\Domains\Inventario\Models\TomaFisicaInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioTomaFisicaApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crear_toma_fisica_por_bodega_prepara_snapshot_y_no_modifica_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-SNAPSHOT');

        $response = $this->crearTomaPorBodega($bodega, 'TF-SNAPSHOT-001');

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', TomaFisicaInventario::ESTADO_BORRADOR)
            ->assertJsonPath('data.tipo', TomaFisicaInventario::TIPO_BODEGA)
            ->assertJsonPath('data.bodega_id', $bodega->id);

        $toma = TomaFisicaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'TF-SNAPSHOT-001')
            ->firstOrFail();

        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);
        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(10.0, (float) $detalle->stock_sistema);
        $this->assertNull($detalle->stock_contado);
        $this->assertEquals(0.0, (float) $detalle->diferencia);
        $this->assertEquals(10.0, (float) $stock->stock_actual);
    }

    public function test_crear_toma_fisica_general_prepara_detalles_de_multiples_bodegas(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodegaA = $this->crearBodega($empresa, ['codigo' => 'BOD-TF-GEN-A']);
        $bodegaB = $this->crearBodega($empresa, ['codigo' => 'BOD-TF-GEN-B']);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodegaA, 5, 'ENT-TF-GEN-A');
        $this->registrarEntrada($producto, $bodegaB, 7, 'ENT-TF-GEN-B');

        $response = $this->postJson('/api/inventario/tomas-fisicas', [
            'tipo' => TomaFisicaInventario::TIPO_GENERAL,
            'referencia' => 'TF-GENERAL-001',
            'motivo' => 'inventario_general',
            'observacion' => 'Toma física general test',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tipo', TomaFisicaInventario::TIPO_GENERAL)
            ->assertJsonPath('data.bodega_id', null);

        $toma = TomaFisicaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'TF-GENERAL-001')
            ->firstOrFail();

        $this->assertEquals(2, $toma->detalles()->count());
        $this->assertEquals(5.0, (float) $this->obtenerDetalleToma($toma, $producto, $bodegaA)->stock_sistema);
        $this->assertEquals(7.0, (float) $this->obtenerDetalleToma($toma, $producto, $bodegaB)->stock_sistema);
    }

    public function test_registrar_conteo_no_modifica_stock_y_calcula_diferencia_positiva(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-CONTEO-POS');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-CONTEO-POS');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 12)
            ->assertOk()
            ->assertJsonPath('success', true);

        $detalle->refresh();
        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(12.0, (float) $detalle->stock_contado);
        $this->assertEquals(2.0, (float) $detalle->diferencia);
        $this->assertEquals(10.0, (float) $stock->stock_actual);
    }

    public function test_registrar_conteo_calcula_diferencia_negativa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-CONTEO-NEG');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-CONTEO-NEG');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 7)
            ->assertOk()
            ->assertJsonPath('success', true);

        $detalle->refresh();

        $this->assertEquals(7.0, (float) $detalle->stock_contado);
        $this->assertEquals(-3.0, (float) $detalle->diferencia);
    }

    public function test_cerrar_toma_fisica_bloquea_revision_sin_modificar_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-CERRAR');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-CERRAR');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 9)->assertOk();

        $this->cerrarToma($toma)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', TomaFisicaInventario::ESTADO_CERRADA);

        $toma->refresh();
        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(TomaFisicaInventario::ESTADO_CERRADA, $toma->estado);
        $this->assertNotNull($toma->cerrado_por);
        $this->assertNotNull($toma->fecha_cierre);
        $this->assertEquals(10.0, (float) $stock->stock_actual);
    }

    public function test_ajuste_positivo_de_toma_fisica_genera_movimiento_y_actualiza_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-AJ-POS');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-AJ-POS');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 12)->assertOk();
        $this->cerrarToma($toma)->assertOk();

        $this->ajustarToma($toma, 'AJ-TF-POS')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', TomaFisicaInventario::ESTADO_AJUSTADA);

        $toma->refresh();
        $detalle->refresh();

        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $movimiento = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'AJ-TF-POS')
            ->firstOrFail();

        $this->assertEquals(TomaFisicaInventario::ESTADO_AJUSTADA, $toma->estado);
        $this->assertEquals(12.0, (float) $stock->stock_actual);
        $this->assertEquals(MovimientoInventario::TIPO_AJUSTE_POSITIVO, $movimiento->tipo);
        $this->assertEquals(2.0, (float) $movimiento->cantidad);
        $this->assertEquals($movimiento->id, (int) $detalle->movimiento_ajuste_id);
    }

    public function test_ajuste_negativo_de_toma_fisica_genera_movimiento_y_actualiza_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-AJ-NEG');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-AJ-NEG');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 7)->assertOk();
        $this->cerrarToma($toma)->assertOk();

        $this->ajustarToma($toma, 'AJ-TF-NEG')
            ->assertOk()
            ->assertJsonPath('success', true);

        $detalle->refresh();

        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $movimiento = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'AJ-TF-NEG')
            ->firstOrFail();

        $this->assertEquals(7.0, (float) $stock->stock_actual);
        $this->assertEquals(MovimientoInventario::TIPO_AJUSTE_NEGATIVO, $movimiento->tipo);
        $this->assertEquals(3.0, (float) $movimiento->cantidad);
        $this->assertEquals($movimiento->id, (int) $detalle->movimiento_ajuste_id);
    }

    public function test_diferencia_cero_no_genera_movimiento_de_ajuste(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-CERO');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-CERO');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 10)->assertOk();
        $this->cerrarToma($toma)->assertOk();
        $this->ajustarToma($toma, 'AJ-TF-CERO')->assertOk();

        $detalle->refresh();
        $toma->refresh();

        $this->assertEquals(TomaFisicaInventario::ESTADO_AJUSTADA, $toma->estado);
        $this->assertNull($detalle->movimiento_ajuste_id);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'AJ-TF-CERO',
        ]);

        $this->assertEquals(10.0, (float) $this->obtenerStockConsolidado($empresa, $producto, $bodega)->stock_actual);
    }

    public function test_ajuste_con_lote_respeta_lote_y_mantiene_stock_consolidado_vs_stock_lotes(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
            'requiere_fecha_vencimiento' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-TF-AJ',
            'fecha_vencimiento' => '2026-12-31',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLote($producto, $bodega, $lote, 10, 'ENT-TF-LOTE');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-LOTE');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega, $lote);

        $this->assertEquals($lote->id, (int) $detalle->lote_id);
        $this->assertEquals(10.0, (float) $detalle->stock_sistema);

        $this->registrarConteo($toma, $detalle, 8)->assertOk();
        $this->cerrarToma($toma)->assertOk();
        $this->ajustarToma($toma, 'AJ-TF-LOTE')->assertOk();

        $detalle->refresh();

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);
        $movimiento = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'AJ-TF-LOTE')
            ->firstOrFail();

        $this->assertEquals(8.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(8.0, (float) $stockLote->stock_actual);
        $this->assertEquals($movimiento->id, (int) $detalle->movimiento_ajuste_id);
        $this->assertStockConsolidadoIgualASumaLotes($empresa, $producto, $bodega);
    }

    public function test_no_permite_ajustar_dos_veces_la_misma_toma(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-DOBLE-AJ');

        $toma = $this->crearTomaIniciadaPorBodega($bodega, 'TF-DOBLE-AJ');
        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->registrarConteo($toma, $detalle, 11)->assertOk();
        $this->cerrarToma($toma)->assertOk();
        $this->ajustarToma($toma, 'AJ-TF-DOBLE-1')->assertOk();

        $this->ajustarToma($toma, 'AJ-TF-DOBLE-2')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'AJ-TF-DOBLE-2',
        ]);
    }

    public function test_no_permite_ajustar_toma_cancelada_ni_contar_toma_ajustada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-ESTADOS');

        $tomaCancelada = $this->crearTomaIniciadaPorBodega($bodega, 'TF-CANCELADA');

        $this->postJson("/api/inventario/tomas-fisicas/{$tomaCancelada->id}/cancelar", [
            'observacion' => 'Cancelada desde test',
        ])->assertOk()
            ->assertJsonPath('data.estado', TomaFisicaInventario::ESTADO_CANCELADA);

        $this->ajustarToma($tomaCancelada, 'AJ-TF-CANCELADA')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $tomaAjustada = $this->crearTomaIniciadaPorBodega($bodega, 'TF-AJUSTADA-BLOQUEO');
        $detalle = $this->obtenerDetalleToma($tomaAjustada, $producto, $bodega);

        $this->registrarConteo($tomaAjustada, $detalle, 10)->assertOk();
        $this->cerrarToma($tomaAjustada)->assertOk();
        $this->ajustarToma($tomaAjustada, 'AJ-TF-AJUSTADA-BLOQUEO')->assertOk();

        $this->registrarConteo($tomaAjustada, $detalle, 9)
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reservas_activas_no_alteran_stock_sistema_de_toma_fisica(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-RESERVA');

        $this->crearReservaApi($producto, $bodega, 4, null, 'PED-TF-RESERVA')
            ->assertCreated()
            ->assertJsonPath('success', true);

        $response = $this->crearTomaPorBodega($bodega, 'TF-RESERVA-SNAPSHOT');

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $toma = TomaFisicaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'TF-RESERVA-SNAPSHOT')
            ->firstOrFail();

        $detalle = $this->obtenerDetalleToma($toma, $producto, $bodega);

        $this->assertEquals(10.0, (float) $detalle->stock_sistema);
        $this->assertEquals(10.0, (float) $this->obtenerStockConsolidado($empresa, $producto, $bodega)->stock_actual);
    }

    public function test_tomas_fisicas_respetan_multiempresa_en_listado_y_detalle(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $otraEmpresa = $this->crearEmpresa();
        $bodegaAjena = $this->crearBodega($otraEmpresa);

        $tomaAjena = TomaFisicaInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'codigo_toma' => 'TF-AJENA',
            'estado' => TomaFisicaInventario::ESTADO_BORRADOR,
            'tipo' => TomaFisicaInventario::TIPO_BODEGA,
            'bodega_id' => $bodegaAjena->id,
            'referencia' => 'TF-AJENA',
            'motivo' => 'inventario_ciclico',
            'observacion' => 'Toma física ajena',
            'creado_por' => null,
        ]);

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-MULTI');
        $this->crearTomaPorBodega($bodega, 'TF-PROPIA')->assertCreated();

        $this->getJson('/api/inventario/tomas-fisicas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing([
                'referencia' => 'TF-AJENA',
            ])
            ->assertJsonFragment([
                'referencia' => 'TF-PROPIA',
            ]);

        $this->getJson("/api/inventario/tomas-fisicas/{$tomaAjena->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_auditor_puede_consultar_tomas_fisicas_pero_no_operar(): void
    {
        [$empresa, $contador] = $this->usuarioContadorConPermisos($this->permisosTomasFisicasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($contador);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-TF-AUDITOR');

        $tomaId = $this->crearTomaPorBodega($bodega, 'TF-AUDITOR')
            ->assertCreated()
            ->json('data.id');

        [, $auditor] = $this->usuarioAuditorConPermisos($this->permisosTomasFisicasLectura());

        Sanctum::actingAs($auditor);

        $this->getJson('/api/inventario/tomas-fisicas')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/inventario/tomas-fisicas/{$tomaId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->crearTomaPorBodega($bodega, 'TF-AUDITOR-BLOQUEADO')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson("/api/inventario/tomas-fisicas/{$tomaId}/iniciar", [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson("/api/inventario/tomas-fisicas/{$tomaId}/cancelar", [
            'observacion' => 'Auditor no debe cancelar',
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_acceder_a_tomas_fisicas_sin_token(): void
    {
        $this->getJson('/api/inventario/tomas-fisicas')
            ->assertUnauthorized();

        $this->postJson('/api/inventario/tomas-fisicas', [])
            ->assertUnauthorized();

        $this->getJson('/api/inventario/tomas-fisicas/1')
            ->assertUnauthorized();

        $this->postJson('/api/inventario/tomas-fisicas/1/ajustar', [])
            ->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de permisos
    |--------------------------------------------------------------------------
    */

    private function permisosTomasFisicasCompleto(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',

            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',

            'inventario.kardex.ver',
            'inventario.valorizacion.ver',

            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',

            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',

            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }

    private function permisosTomasFisicasLectura(): array
    {
        return [
            'inventario.tomas_fisicas.ver',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de requests
    |--------------------------------------------------------------------------
    */

    private function crearTomaPorBodega(Bodega $bodega, string $referencia)
    {
        return $this->postJson('/api/inventario/tomas-fisicas', [
            'tipo' => TomaFisicaInventario::TIPO_BODEGA,
            'bodega_id' => $bodega->id,
            'referencia' => $referencia,
            'motivo' => 'inventario_ciclico',
            'observacion' => 'Toma física creada desde test',
        ]);
    }

    private function crearTomaIniciadaPorBodega(Bodega $bodega, string $referencia): TomaFisicaInventario
    {
        $id = $this->crearTomaPorBodega($bodega, $referencia)
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/inventario/tomas-fisicas/{$id}/iniciar", [])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', TomaFisicaInventario::ESTADO_EN_CONTEO);

        return TomaFisicaInventario::findOrFail($id);
    }

    private function registrarConteo(
        TomaFisicaInventario $toma,
        TomaFisicaDetalleInventario $detalle,
        float $stockContado
    ) {
        return $this->postJson("/api/inventario/tomas-fisicas/{$toma->id}/conteos", [
            'detalles' => [
                [
                    'detalle_id' => $detalle->id,
                    'stock_contado' => $stockContado,
                    'observacion' => 'Conteo validado desde test',
                ],
            ],
        ]);
    }

    private function cerrarToma(TomaFisicaInventario $toma)
    {
        return $this->postJson("/api/inventario/tomas-fisicas/{$toma->id}/cerrar", [
            'observacion' => 'Toma cerrada desde test',
        ]);
    }

    private function ajustarToma(TomaFisicaInventario $toma, string $referencia)
    {
        return $this->postJson("/api/inventario/tomas-fisicas/{$toma->id}/ajustar", [
            'referencia' => $referencia,
            'motivo' => MovimientoInventario::MOTIVO_CORRECCION_STOCK,
            'observacion' => 'Ajuste generado desde test de toma física',
            'costo_unitario' => 100,
        ]);
    }

    private function registrarEntrada(
        Producto $producto,
        Bodega $bodega,
        float $cantidad,
        string $referencia
    ): void {
        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 100,
            'referencia' => $referencia,
            'motivo' => MovimientoInventario::MOTIVO_COMPRA,
            'observacion' => 'Entrada auxiliar para toma física',
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }

    private function registrarEntradaConLote(
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote,
        float $cantidad,
        string $referencia
    ): void {
        $this->postJson('/api/inventario/movimientos', [
            'tipo' => MovimientoInventario::TIPO_ENTRADA,
            'producto_id' => $producto->id,
            'bodega_destino_id' => $bodega->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 100,
            'lote_id' => $lote->id,
            'referencia' => $referencia,
            'motivo' => MovimientoInventario::MOTIVO_COMPRA,
            'observacion' => 'Entrada auxiliar con lote para toma física',
        ])->assertCreated()
            ->assertJsonPath('success', true);
    }

    private function crearReservaApi(
        Producto $producto,
        Bodega $bodega,
        float $cantidad,
        ?LoteInventario $lote,
        string $referencia
    ) {
        $detalle = [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad' => $cantidad,
        ];

        if ($lote !== null) {
            $detalle['lote_id'] = $lote->id;
        }

        return $this->postJson('/api/inventario/reservas', [
            'referencia' => $referencia,
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva auxiliar para test de toma física',
            'detalles' => [
                $detalle,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de datos
    |--------------------------------------------------------------------------
    */

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'rut' => $this->rutUnico(),
            'razon_social' => 'Empresa Inventario ' . uniqid(),
        ]);
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = $this->obtenerUnidadBase();

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'PROD-' . strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Toma Fisica Test',
            'descripcion' => 'Producto para pruebas de toma física',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780' . random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'bodega_defecto_id' => null,
            'permite_merma' => true,
            'maneja_lotes' => false,
            'requiere_fecha_vencimiento' => false,
            'activo' => true,
        ], $overrides));
    }

    private function crearBodega(Empresa $empresa, array $overrides = []): Bodega
    {
        return Bodega::create(array_merge([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Toma Fisica Test',
            'direccion' => 'Santiago, Chile',
            'estado' => 'ACTIVA',
        ], $overrides));
    }

    private function crearLote(Empresa $empresa, Producto $producto, array $overrides = []): LoteInventario
    {
        return LoteInventario::create(array_merge([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOT-' . strtoupper(substr(uniqid(), -8)),
            'fecha_fabricacion' => null,
            'fecha_vencimiento' => null,
            'observacion' => 'Lote creado por test de toma física',
            'activo' => true,
        ], $overrides));
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

    private function obtenerDetalleToma(
        TomaFisicaInventario $toma,
        Producto $producto,
        Bodega $bodega,
        ?LoteInventario $lote = null
    ): TomaFisicaDetalleInventario {
        return TomaFisicaDetalleInventario::query()
            ->where('empresa_id', $toma->empresa_id)
            ->where('toma_fisica_id', $toma->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->when($lote === null, function ($query) {
                $query->whereNull('lote_id');
            }, function ($query) use ($lote) {
                $query->where('lote_id', $lote->id);
            })
            ->firstOrFail();
    }

    private function obtenerStockConsolidado(Empresa $empresa, Producto $producto, Bodega $bodega): StockProducto
    {
        return StockProducto::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();
    }

    private function obtenerStockLote(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega,
        LoteInventario $lote
    ): StockLoteInventario {
        return StockLoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->where('lote_id', $lote->id)
            ->firstOrFail();
    }

    private function assertStockConsolidadoIgualASumaLotes(
        Empresa $empresa,
        Producto $producto,
        Bodega $bodega
    ): void {
        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $sumaLotes = StockLoteInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->sum('stock_actual');

        $this->assertEquals(
            round((float) $stockConsolidado->stock_actual, 4),
            round((float) $sumaLotes, 4),
            "El stock consolidado no coincide con la suma de stock_lotes para bodega {$bodega->id}."
        );
    }

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}