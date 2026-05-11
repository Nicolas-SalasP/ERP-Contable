<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaConsumoInventario;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockLoteInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

class InventarioReservaApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_crear_reserva_valida_sin_lote_no_descuenta_stock_fisico(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-RES-SIN-LOTE');

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-001',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva sin lote',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'bodega_id' => $bodega->id,
                    'cantidad' => 4,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_ACTIVA)
            ->assertJsonPath('data.detalles.0.producto_id', $producto->id)
            ->assertJsonPath('data.detalles.0.bodega_id', $bodega->id);

        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(10.0, (float) $stock->stock_actual);

        $detalle = ReservaDetalleInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)
            ->firstOrFail();

        $this->assertNull($detalle->lote_id);
        $this->assertEquals(4.0, (float) $detalle->cantidad_reservada);
        $this->assertEquals(0.0, (float) $detalle->cantidad_consumida);
        $this->assertEquals(0.0, (float) $detalle->cantidad_liberada);
        $this->assertEquals(4.0, $detalle->cantidadPendiente());
    }

    public function test_crear_reserva_valida_con_lote_no_descuenta_stock_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLote($producto, $bodega, $lote, 10, 'ENT-RES-LOTE');

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-LOTE-001',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva con lote',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'bodega_id' => $bodega->id,
                    'lote_id' => $lote->id,
                    'cantidad' => 3,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_ACTIVA)
            ->assertJsonPath('data.detalles.0.lote_id', $lote->id);

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);

        $this->assertEquals(10.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(10.0, (float) $stockLote->stock_actual);
    }

    public function test_rechaza_reserva_sin_lote_cuando_producto_maneja_lotes(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLote($producto, $bodega, $lote, 10, 'ENT-EXIGE-LOTE');

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-SIN-LOTE',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva inválida sin lote',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'bodega_id' => $bodega->id,
                    'cantidad' => 2,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_reservas', [
            'empresa_id' => $empresa->id,
            'referencia' => 'PED-SIN-LOTE',
        ]);
    }

    public function test_rechaza_reserva_con_lote_de_otro_producto(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $otroProducto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $loteOtroProducto = $this->crearLote($empresa, $otroProducto);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-LOTE-OTRO-PRODUCTO',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva inválida',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'bodega_id' => $bodega->id,
                    'lote_id' => $loteOtroProducto->id,
                    'cantidad' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_reservas', [
            'empresa_id' => $empresa->id,
            'referencia' => 'PED-LOTE-OTRO-PRODUCTO',
        ]);
    }

    public function test_rechaza_reserva_con_lote_de_otra_empresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $productoOtraEmpresa = $this->crearProducto($otraEmpresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $loteOtraEmpresa = $this->crearLote($otraEmpresa, $productoOtraEmpresa);

        Sanctum::actingAs($usuario);

        $response = $this->postJson('/api/inventario/reservas', [
            'referencia' => 'PED-LOTE-OTRA-EMPRESA',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva inválida',
            'detalles' => [
                [
                    'producto_id' => $producto->id,
                    'bodega_id' => $bodega->id,
                    'lote_id' => $loteOtraEmpresa->id,
                    'cantidad' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_rechaza_reserva_si_stock_disponible_es_insuficiente(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 5, 'ENT-STOCK-INSUF');

        $this->crearReservaApi($producto, $bodega, 4, null, 'PED-RESERVA-4')
            ->assertCreated();

        $response = $this->crearReservaApi($producto, $bodega, 2, null, 'PED-RESERVA-2');

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEquals(1, ReservaInventario::query()
            ->where('empresa_id', $empresa->id)
            ->whereIn('referencia', ['PED-RESERVA-4', 'PED-RESERVA-2'])
            ->count());
    }

    public function test_cancelar_reserva_libera_disponibilidad(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-CANCELA-RES');

        $reservaId = $this->crearReservaApi($producto, $bodega, 4, null, 'PED-CANCELAR')
            ->assertCreated()
            ->json('data.id');

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 4)
            ->assertJsonPath('data.0.stock_disponible', 6);

        $this->postJson("/api/inventario/reservas/{$reservaId}/cancelar", [
            'observacion' => 'Cancelación por cambio de pedido',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_CANCELADA);

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 0)
            ->assertJsonPath('data.0.stock_disponible', 10);
    }

    public function test_liberar_parcialmente_reserva_actualiza_disponibilidad(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-LIBERA-RES');

        $reservaResponse = $this->crearReservaApi($producto, $bodega, 6, null, 'PED-LIBERAR')
            ->assertCreated();

        $reservaId = $reservaResponse->json('data.id');
        $detalleId = $reservaResponse->json('data.detalles.0.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/liberar", [
            'observacion' => 'Liberación parcial',
            'detalles' => [
                [
                    'detalle_id' => $detalleId,
                    'cantidad' => 2,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_PARCIALMENTE_LIBERADA);

        $detalle = ReservaDetalleInventario::findOrFail($detalleId);

        $this->assertEquals(2.0, (float) $detalle->cantidad_liberada);
        $this->assertEquals(4.0, $detalle->cantidadPendiente());

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 4)
            ->assertJsonPath('data.0.stock_disponible', 6);
    }

    public function test_consumir_reserva_genera_movimiento_de_salida_y_descuenta_stock(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-CONSUME-RES');

        $reservaId = $this->crearReservaApi($producto, $bodega, 4, null, 'PED-CONSUMIR')
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-RES-001',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Salida generada desde reserva',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_CONSUMIDA);

        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(6.0, (float) $stock->stock_actual);

        $movimiento = MovimientoInventario::query()
            ->where('empresa_id', $empresa->id)
            ->where('referencia', 'SAL-RES-001')
            ->firstOrFail();

        $this->assertEquals(MovimientoInventario::TIPO_SALIDA, $movimiento->tipo);
        $this->assertEquals(4.0, (float) $movimiento->cantidad);

        $this->assertDatabaseHas('inventario_reserva_consumos', [
            'empresa_id' => $empresa->id,
            'reserva_id' => $reservaId,
            'movimiento_inventario_id' => $movimiento->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
        ]);
    }

    public function test_consumir_reserva_con_lote_descuenta_stock_lote_y_stock_consolidado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);
        $lote = $this->crearLote($empresa, $producto);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLote($producto, $bodega, $lote, 10, 'ENT-CONSUME-LOTE');

        $reservaId = $this->crearReservaApi($producto, $bodega, 3, $lote, 'PED-CONSUMIR-LOTE')
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-RES-LOTE-001',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Salida desde reserva con lote',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_CONSUMIDA);

        $stockConsolidado = $this->obtenerStockConsolidado($empresa, $producto, $bodega);
        $stockLote = $this->obtenerStockLote($empresa, $producto, $bodega, $lote);

        $this->assertEquals(7.0, (float) $stockConsolidado->stock_actual);
        $this->assertEquals(7.0, (float) $stockLote->stock_actual);

        $this->assertStockConsolidadoIgualASumaLotes($empresa, $producto, $bodega);
    }

    public function test_consumo_parcial_deja_reserva_parcialmente_consumida(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-CONSUMO-PARCIAL');

        $reservaResponse = $this->crearReservaApi($producto, $bodega, 6, null, 'PED-CONSUMO-PARCIAL')
            ->assertCreated();

        $reservaId = $reservaResponse->json('data.id');
        $detalleId = $reservaResponse->json('data.detalles.0.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-RES-PARCIAL',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Consumo parcial',
            'detalles' => [
                [
                    'detalle_id' => $detalleId,
                    'cantidad' => 2,
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estado', ReservaInventario::ESTADO_PARCIALMENTE_CONSUMIDA);

        $detalle = ReservaDetalleInventario::findOrFail($detalleId);

        $this->assertEquals(2.0, (float) $detalle->cantidad_consumida);
        $this->assertEquals(4.0, $detalle->cantidadPendiente());

        $stock = $this->obtenerStockConsolidado($empresa, $producto, $bodega);

        $this->assertEquals(8.0, (float) $stock->stock_actual);
    }

    public function test_no_permite_consumir_reserva_cancelada(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-CANCELADA-NO-CONSUME');

        $reservaId = $this->crearReservaApi($producto, $bodega, 4, null, 'PED-CANCELADA-NO-CONSUME')
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/cancelar", [
            'observacion' => 'Cancelada antes de consumir',
        ])->assertOk();

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-NO-DEBE-CREARSE',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Intento inválido',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('inventario_movimientos', [
            'empresa_id' => $empresa->id,
            'referencia' => 'SAL-NO-DEBE-CREARSE',
        ]);
    }

    public function test_no_permite_liberar_mas_de_lo_pendiente(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-LIBERA-MAS');

        $reservaResponse = $this->crearReservaApi($producto, $bodega, 3, null, 'PED-LIBERA-MAS')
            ->assertCreated();

        $reservaId = $reservaResponse->json('data.id');
        $detalleId = $reservaResponse->json('data.detalles.0.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/liberar", [
            'observacion' => 'Intento inválido',
            'detalles' => [
                [
                    'detalle_id' => $detalleId,
                    'cantidad' => 4,
                ],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $detalle = ReservaDetalleInventario::findOrFail($detalleId);

        $this->assertEquals(0.0, (float) $detalle->cantidad_liberada);
        $this->assertEquals(3.0, $detalle->cantidadPendiente());
    }

    public function test_auditor_puede_consultar_reservas_y_disponibilidad_pero_no_operar(): void
    {
        [$empresa, $contador] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($contador);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-AUDITOR-RES');

        $reservaId = $this->crearReservaApi($producto, $bodega, 3, null, 'PED-AUDITOR')
            ->assertCreated()
            ->json('data.id');

        [, $auditor] = $this->usuarioAuditorConPermisos($this->permisosReservasLectura());

        Sanctum::actingAs($auditor);

        $this->getJson('/api/inventario/reservas')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/inventario/disponibilidad')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->crearReservaApi($producto, $bodega, 1, null, 'PED-AUDITOR-BLOQUEADO')
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson("/api/inventario/reservas/{$reservaId}/cancelar", [
            'observacion' => 'Auditor no debe cancelar',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-AUDITOR-BLOQUEADO',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Auditor no debe consumir',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reservas_respetan_multiempresa_en_listado_y_detalle(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosReservasCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $productoAjeno = $this->crearProducto($otraEmpresa);
        $bodegaAjena = $this->crearBodega($otraEmpresa);

        $reservaAjena = ReservaInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'codigo_reserva' => 'RES-AJENA',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'referencia' => 'PED-AJENO',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva de otra empresa',
            'reservado_por' => null,
            'fecha_reserva' => now(),
        ]);

        ReservaDetalleInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'reserva_id' => $reservaAjena->id,
            'producto_id' => $productoAjeno->id,
            'bodega_id' => $bodegaAjena->id,
            'lote_id' => null,
            'cantidad_reservada' => 5,
            'cantidad_consumida' => 0,
            'cantidad_liberada' => 0,
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-MULTIEMPRESA-RES');

        $this->crearReservaApi($producto, $bodega, 2, null, 'PED-PROPIO')
            ->assertCreated();

        $this->getJson('/api/inventario/reservas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing([
                'referencia' => 'PED-AJENO',
            ])
            ->assertJsonFragment([
                'referencia' => 'PED-PROPIO',
            ]);

        $this->getJson("/api/inventario/reservas/{$reservaAjena->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_no_permite_acceder_a_reservas_sin_token(): void
    {
        $this->getJson('/api/inventario/reservas')
            ->assertUnauthorized();

        $this->postJson('/api/inventario/reservas', [])
            ->assertUnauthorized();

        $this->getJson('/api/inventario/disponibilidad')
            ->assertUnauthorized();
    }

    private function permisosReservasCompleto(): array
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

            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',

            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',

            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',
        ];
    }

    private function permisosReservasLectura(): array
    {
        return [
            'inventario.productos.ver',
            'inventario.bodegas.ver',
            'inventario.movimientos.ver',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver',
            'inventario.lotes.ver',
            'inventario.reservas.ver',
            'inventario.disponibilidad.ver',
        ];
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
            'observacion' => 'Reserva creada desde test',
            'detalles' => [
                $detalle,
            ],
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
            'motivo' => 'compra',
            'observacion' => 'Entrada auxiliar para reservas',
        ])
            ->assertCreated()
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
            'motivo' => 'compra',
            'observacion' => 'Entrada auxiliar con lote para reservas',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

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
            'nombre' => 'Producto Reserva Test',
            'descripcion' => 'Producto para pruebas de reservas',
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
            'nombre' => 'Bodega Reserva Test',
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
            'observacion' => 'Lote creado por test de reservas',
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