<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Core\Models\Empresa;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\ReservaDetalleInventario;
use App\Domains\Inventario\Models\ReservaInventario;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Contrato v2 (Etapa nueva): precio_venta_bruto calculado por el ERP, stock_disponible
 * descontando reservas activas, y verificacion de que v1 sigue funcionando sin cambios.
 */
class InventarioProductoApiV2Test extends TestCase
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
            'visible_web' => false,
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

    private function crearReservaActiva(Empresa $empresa, Producto $producto, Bodega $bodega, float $cantidad): void
    {
        $reserva = ReservaInventario::create([
            'empresa_id' => $empresa->id,
            'codigo_reserva' => 'RES-'.strtoupper(substr(uniqid(), -8)),
            'estado' => ReservaInventario::ESTADO_ACTIVA,
            'fecha_reserva' => now(),
        ]);

        ReservaDetalleInventario::create([
            'empresa_id' => $empresa->id,
            'reserva_id' => $reserva->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad_reservada' => $cantidad,
            'cantidad_consumida' => 0,
            'cantidad_liberada' => 0,
        ]);
    }

    public function test_v1_sigue_funcionando_sin_precio_bruto_ni_stock_disponible(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'V1-001']);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v1/inventario/productos');

        $respuesta->assertOk();
        $this->assertArrayNotHasKey('precio_venta_bruto', $respuesta->json('data.0'));
        $this->assertArrayNotHasKey('stock_disponible', $respuesta->json('data.0'));
    }

    public function test_v2_calcula_precio_bruto_para_producto_afecto_a_iva(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'V2-AFECTO', 'precio_venta_neto' => 1000, 'afecto_iva' => true]);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v2/inventario/productos');

        $respuesta->assertOk();
        $producto = collect($respuesta->json('data'))->firstWhere('sku', 'V2-AFECTO');

        $this->assertEquals(1000.0, $producto['precio_venta_neto']);
        $tasaIva = (float) config('fiscal.tasa_iva');
        $this->assertEquals(round(1000 + round(1000 * $tasaIva, 2), 2), $producto['precio_venta_bruto']);
    }

    public function test_v2_precio_bruto_es_igual_al_neto_si_esta_exento(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'V2-EXENTO', 'precio_venta_neto' => 500, 'afecto_iva' => false]);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v2/inventario/productos');

        $respuesta->assertOk();
        $producto = collect($respuesta->json('data'))->firstWhere('sku', 'V2-EXENTO');

        $this->assertEquals(500.0, $producto['precio_venta_bruto']);
    }

    public function test_v2_stock_disponible_descuenta_reservas_activas(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['sku' => 'V2-STOCK']);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 100);
        $this->crearReservaActiva($empresa, $producto, $bodega, 30);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v2/inventario/productos/V2-STOCK');

        $respuesta->assertOk();
        $this->assertEquals(100.0, $respuesta->json('data.stock_actual_total'));
        $this->assertEquals(70.0, $respuesta->json('data.stock_disponible'));
    }

    public function test_v2_stock_disponible_ignora_reservas_canceladas(): void
    {
        $empresa = $this->crearEmpresa();
        $producto = $this->crearProducto($empresa, ['sku' => 'V2-CANCELADA']);
        $bodega = $this->crearBodega($empresa);
        $this->crearStock($empresa, $producto, $bodega, 50);

        $reserva = ReservaInventario::create([
            'empresa_id' => $empresa->id,
            'codigo_reserva' => 'RES-CANC-'.strtoupper(substr(uniqid(), -6)),
            'estado' => ReservaInventario::ESTADO_CANCELADA,
            'fecha_reserva' => now(),
        ]);

        ReservaDetalleInventario::create([
            'empresa_id' => $empresa->id,
            'reserva_id' => $reserva->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'cantidad_reservada' => 20,
            'cantidad_consumida' => 0,
            'cantidad_liberada' => 0,
        ]);

        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v2/inventario/productos/V2-CANCELADA');

        $respuesta->assertOk();
        $this->assertEquals(50.0, $respuesta->json('data.stock_disponible'));
    }

    public function test_v2_toggle_visible_web_usa_el_scope_escribir(): void
    {
        $empresa = $this->crearEmpresa();
        $this->crearProducto($empresa, ['sku' => 'V2-TOGGLE', 'visible_web' => false]);
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->patchJson('/api/integraciones/v2/inventario/productos/V2-TOGGLE/visible-web', ['visible_web' => true])
            ->assertOk()
            ->assertJsonPath('data.visible_web', true);
    }

    public function test_v2_index_sin_scope_leer_es_rechazado(): void
    {
        $empresa = $this->crearEmpresa();
        $token = $this->habilitarModuloYEmitirKey($empresa, ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/integraciones/v2/inventario/productos')
            ->assertForbidden();
    }
}
