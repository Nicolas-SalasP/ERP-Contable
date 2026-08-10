<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Core\Models\Empresa;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * POST /reservas y DELETE /reservas/{id} (Fase 2): reservar/liberar stock desde un tercero
 * (Tenri-Web-Page) contra InventarioReservaService real, con scope ventas:escribir.
 */
class ReservaVentaIntegracionApiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function habilitarModuloYEmitirKey(Empresa $empresa, array $scopes): string
    {
        $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);

        $emitida = app(IntegracionApiKeyService::class)->emitir($empresa->id, 'Test', $scopes);

        return $emitida['token'];
    }

    private function crearProducto(Empresa $empresa, array $overrides = []): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            ['nombre' => 'Unidad', 'permite_decimal' => false, 'activo' => true]
        );

        return Producto::create(array_merge([
            'empresa_id' => $empresa->id,
            'sku' => 'SKU-'.strtoupper(substr(uniqid(), -8)),
            'nombre' => 'Producto Test',
            'descripcion' => 'Descripcion de prueba',
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => 100,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'codigo_barra' => '780'.random_int(1000000000, 9999999999),
            'stock_minimo' => 0,
            'permite_merma' => true,
            'activo' => true,
            'visible_web' => true,
        ], $overrides));
    }

    private function crearBodega(Empresa $empresa): Bodega
    {
        return Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-'.strtoupper(substr(uniqid(), -6)),
            'nombre' => 'Bodega Test',
            'estado' => 'ACTIVA',
        ]);
    }

    private function crearStock(Empresa $empresa, Producto $producto, Bodega $bodega, float $cantidad): StockProducto
    {
        return StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $cantidad,
            'costo_promedio' => 100,
            'valor_total' => $cantidad * 100,
        ]);
    }

    public function test_reservar_con_stock_suficiente_crea_la_reserva(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['sku' => 'RES-001']);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', [
                'sku' => 'RES-001',
                'cantidad' => 3,
                'referencia_externa' => 'PEDIDO-WEB-1',
            ]);

        $respuesta->assertCreated();
        $this->assertNotNull($respuesta->json('data.reserva_id'));
        $this->assertNotNull($respuesta->json('data.expira_en'));

        $reserva = ReservaInventario::where('empresa_id', $empresa->id)->firstOrFail();
        $this->assertEquals('ACTIVA', $reserva->estado);
        $this->assertEquals('PEDIDO-WEB-1', $reserva->referencia);
        $this->assertEquals('INTEGRACIONES', $reserva->origen_modulo);
        $this->assertEqualsWithDelta(48 * 60, now()->diffInMinutes($reserva->fecha_expiracion), 5);
    }

    public function test_reservar_sin_stock_suficiente_devuelve_422(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'RES-SINSTOCK']);
        $this->crearBodega($empresa);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', [
                'sku' => 'RES-SINSTOCK',
                'cantidad' => 5,
            ]);

        $respuesta->assertStatus(422);
        $this->assertSame(0, ReservaInventario::where('empresa_id', $empresa->id)->count());
    }

    public function test_reservar_sin_scope_ventas_escribir_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'RES-SINSCOPE']);
        // La key solo tiene scope de inventario, no de ventas.
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => 'RES-SINSCOPE', 'cantidad' => 1])
            ->assertForbidden();
    }

    public function test_reservar_sku_de_otra_empresa_devuelve_404(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $this->crearProducto($empresaB, ['sku' => 'RES-OTRAEMPRESA']);
        $token = $this->habilitarModuloYEmitirKey($empresaA, ['ventas:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => 'RES-OTRAEMPRESA', 'cantidad' => 1])
            ->assertNotFound();
    }

    public function test_liberar_reserva_la_cancela_y_libera_disponibilidad(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['sku' => 'RES-LIB']);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 10);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['ventas:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => 'RES-LIB', 'cantidad' => 4])
            ->json('data.reserva_id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->deleteJson("/api/integraciones/v2/reservas/{$reservaId}")
            ->assertOk();

        $reserva = ReservaInventario::findOrFail($reservaId);
        $this->assertEquals(ReservaInventario::ESTADO_CANCELADA, $reserva->estado);
    }

    public function test_liberar_reserva_de_otra_empresa_devuelve_404(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $producto = $this->crearProducto($empresaB, ['sku' => 'RES-CROSS']);
        $bodega = $this->crearBodega($empresaB);
        $this->crearStock($empresaB, $producto, $bodega, 10);
        $tokenB = $this->habilitarModuloYEmitirKey($empresaB, ['ventas:escribir']);

        $reservaId = $this->withHeaders(['Authorization' => 'Bearer '.$tokenB])
            ->postJson('/api/integraciones/v2/reservas', ['sku' => 'RES-CROSS', 'cantidad' => 2])
            ->json('data.reserva_id');

        $tokenA = $this->habilitarModuloYEmitirKey($empresaA, ['ventas:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenA])
            ->deleteJson("/api/integraciones/v2/reservas/{$reservaId}")
            ->assertNotFound();

        $reserva = ReservaInventario::findOrFail($reservaId);
        $this->assertEquals(ReservaInventario::ESTADO_ACTIVA, $reserva->estado, 'La reserva de la empresa B no debe verse afectada por la key de la empresa A.');
    }
}
