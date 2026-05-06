<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\LoteInventario;
use App\Domains\Inventario\Models\MovimientoInventario;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaInventarioTest;
use Tests\TestCase;

class InventarioDisponibilidadApiTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTest;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararUsuariosInventarioDemo();
    }

    public function test_disponibilidad_considera_reservas_activas(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-DISP-ACTIVA');
        $this->crearReserva($producto, $bodega, 4, null, 'PED-DISP-ACTIVA');

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.producto_id', $producto->id)
            ->assertJsonPath('data.0.bodega_id', $bodega->id)
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 4)
            ->assertJsonPath('data.0.stock_disponible', 6);
    }

    public function test_disponibilidad_por_lote_considera_reservas_activas_por_lote(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $producto = $this->crearProducto($empresa, [
            'maneja_lotes' => true,
        ]);

        $bodega = $this->crearBodega($empresa);

        $loteA = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-DISP-A',
        ]);

        $loteB = $this->crearLote($empresa, $producto, [
            'codigo_lote' => 'LOT-DISP-B',
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntradaConLote($producto, $bodega, $loteA, 10, 'ENT-DISP-LOTE-A');
        $this->registrarEntradaConLote($producto, $bodega, $loteB, 5, 'ENT-DISP-LOTE-B');

        $this->crearReserva($producto, $bodega, 4, $loteA, 'PED-DISP-LOTE-A');

        $response = $this->getJson(
            '/api/inventario/productos/' . $producto->id . '/disponibilidad?bodega_id=' . $bodega->id . '&incluir_lotes=1'
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.totales.stock_fisico', 15)
            ->assertJsonPath('data.totales.stock_reservado', 4)
            ->assertJsonPath('data.totales.stock_disponible', 11);

        $lotes = collect($response->json('data.lotes'));

        $loteAJson = $lotes->firstWhere('lote_id', $loteA->id);
        $loteBJson = $lotes->firstWhere('lote_id', $loteB->id);

        $this->assertNotNull($loteAJson);
        $this->assertNotNull($loteBJson);

        $this->assertEquals(10, $loteAJson['stock_fisico']);
        $this->assertEquals(4, $loteAJson['stock_reservado']);
        $this->assertEquals(6, $loteAJson['stock_disponible']);

        $this->assertEquals(5, $loteBJson['stock_fisico']);
        $this->assertEquals(0, $loteBJson['stock_reservado']);
        $this->assertEquals(5, $loteBJson['stock_disponible']);
    }

    public function test_reserva_cancelada_no_compromete_disponibilidad(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-DISP-CANCELADA');

        $reservaId = $this->crearReserva($producto, $bodega, 4, null, 'PED-DISP-CANCELADA')
            ->json('data.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/cancelar", [
            'observacion' => 'Cancelación para disponibilidad',
        ])->assertOk();

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 0)
            ->assertJsonPath('data.0.stock_disponible', 10);
    }

    public function test_liberacion_parcial_reduce_stock_reservado(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-DISP-LIBERA');

        $reservaResponse = $this->crearReserva($producto, $bodega, 6, null, 'PED-DISP-LIBERA');

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
        ])->assertOk();

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.stock_fisico', 10)
            ->assertJsonPath('data.0.stock_reservado', 4)
            ->assertJsonPath('data.0.stock_disponible', 6);
    }

    public function test_consumo_de_reserva_reduce_stock_fisico_y_elimina_compromiso_pendiente(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-DISP-CONSUME');

        $reservaId = $this->crearReserva($producto, $bodega, 4, null, 'PED-DISP-CONSUME')
            ->json('data.id');

        $this->postJson("/api/inventario/reservas/{$reservaId}/consumir", [
            'referencia' => 'SAL-DISP-CONSUME',
            'motivo' => 'consumo_reserva',
            'observacion' => 'Consumo para disponibilidad',
        ])->assertOk();

        $this->getJson('/api/inventario/disponibilidad?producto_id=' . $producto->id . '&bodega_id=' . $bodega->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.stock_fisico', 6)
            ->assertJsonPath('data.0.stock_reservado', 0)
            ->assertJsonPath('data.0.stock_disponible', 6);
    }

    public function test_disponibilidad_respeta_multiempresa(): void
    {
        [$empresa, $usuario] = $this->usuarioContadorConPermisos($this->permisosDisponibilidadCompleto());

        $otraEmpresa = $this->crearEmpresa();

        $producto = $this->crearProducto($empresa);
        $bodega = $this->crearBodega($empresa);

        $productoAjeno = $this->crearProducto($otraEmpresa);
        $bodegaAjena = $this->crearBodega($otraEmpresa);

        $reservaAjena = ReservaInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'codigo_reserva' => 'RES-DISP-AJENA',
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'referencia' => 'PED-DISP-AJENO',
            'motivo' => 'reserva_comercial',
            'observacion' => 'Reserva ajena',
            'fecha_reserva' => now(),
        ]);

        ReservaDetalleInventario::create([
            'empresa_id' => $otraEmpresa->id,
            'reserva_id' => $reservaAjena->id,
            'producto_id' => $productoAjeno->id,
            'bodega_id' => $bodegaAjena->id,
            'lote_id' => null,
            'cantidad_reservada' => 99,
            'cantidad_consumida' => 0,
            'cantidad_liberada' => 0,
        ]);

        Sanctum::actingAs($usuario);

        $this->registrarEntrada($producto, $bodega, 10, 'ENT-DISP-MULTIEMPRESA');

        $this->getJson('/api/inventario/disponibilidad')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing([
                'producto_id' => $productoAjeno->id,
            ])
            ->assertJsonFragment([
                'producto_id' => $producto->id,
            ]);
    }

    private function permisosDisponibilidadCompleto(): array
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
        ];
    }

    private function crearReserva(
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
            'observacion' => 'Reserva creada desde test de disponibilidad',
            'detalles' => [
                $detalle,
            ],
        ])->assertCreated()
            ->assertJsonPath('success', true);
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
            'observacion' => 'Entrada auxiliar para disponibilidad',
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
            'observacion' => 'Entrada auxiliar con lote para disponibilidad',
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
            'nombre' => 'Producto Disponibilidad Test',
            'descripcion' => 'Producto para pruebas de disponibilidad',
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
            'nombre' => 'Bodega Disponibilidad Test',
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
            'observacion' => 'Lote creado por test de disponibilidad',
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

    private function rutUnico(): string
    {
        return (string) random_int(70000000, 99999999) . '-' . random_int(0, 9);
    }
}