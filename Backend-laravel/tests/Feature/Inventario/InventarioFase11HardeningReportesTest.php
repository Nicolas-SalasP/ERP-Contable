<?php

namespace Tests\Feature\Inventario;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\Bodega;
use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Models\StockProducto;
use App\Domains\Inventario\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventarioFase11HardeningReportesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_dashboard_requiere_token_sanctum(): void
    {
        $this->getJson('/api/inventario/dashboard')
            ->assertUnauthorized();
    }

    public function test_catalogos_rechaza_usuario_autenticado_sin_permisos_de_inventario(): void
    {
        $usuario = $this->crearUsuarioConPermisos([]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/catalogos')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reporte_stock_exige_permiso_explicito_de_reportes(): void
    {
        $usuario = $this->crearUsuarioConPermisos([
            'inventario.productos.ver',
            'inventario.bodegas.ver',
        ]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/reportes/stock')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reporte_stock_filtra_por_empresa_del_usuario(): void
    {
        $empresaLocal = $this->crearEmpresa('77900001-1', 'Empresa Local Fase 11');
        $empresaAjena = $this->crearEmpresa('77900002-2', 'Empresa Ajena Fase 11');

        $usuario = $this->crearUsuarioConPermisos([
            'inventario.reportes.ver',
        ], $empresaLocal);

        $this->crearProductoConStock($empresaLocal, 'SKU-LOCAL-F11', 'Producto Local F11', 12, 1200);
        $this->crearProductoConStock($empresaAjena, 'SKU-AJENO-F11', 'Producto Ajeno F11', 30, 3000);

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/inventario/reportes/stock?limit=100');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $skus = collect($response->json('data'))->pluck('producto_sku')->all();

        $this->assertContains('SKU-LOCAL-F11', $skus);
        $this->assertNotContains('SKU-AJENO-F11', $skus);
    }

    public function test_exportar_csv_exige_permiso_especifico_de_exportacion(): void
    {
        $empresa = $this->crearEmpresa('77900003-3', 'Empresa CSV Sin Permiso F11');
        $usuario = $this->crearUsuarioConPermisos([
            'inventario.reportes.ver',
        ], $empresa);

        $this->crearProductoConStock($empresa, 'SKU-CSV-BLOCK-F11', 'Producto CSV bloqueado', 5, 500);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/reportes/stock/exportar-csv')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_exportar_csv_respeta_multiempresa(): void
    {
        $empresaLocal = $this->crearEmpresa('77900004-4', 'Empresa CSV Local F11');
        $empresaAjena = $this->crearEmpresa('77900005-5', 'Empresa CSV Ajena F11');

        $usuario = $this->crearUsuarioConPermisos([
            'inventario.reportes.ver',
            'inventario.reportes.exportar',
        ], $empresaLocal);

        $this->crearProductoConStock($empresaLocal, 'SKU-CSV-LOCAL-F11', 'Producto CSV Local', 9, 900);
        $this->crearProductoConStock($empresaAjena, 'SKU-CSV-AJENO-F11', 'Producto CSV Ajeno', 99, 9900);

        Sanctum::actingAs($usuario);

        $response = $this->get('/api/inventario/reportes/stock/exportar-csv');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('SKU-CSV-LOCAL-F11', $csv);
        $this->assertStringNotContainsString('SKU-CSV-AJENO-F11', $csv);
    }

    public function test_module_keys_y_backend_usan_la_misma_fuente_de_permisos(): void
    {
        $empresa = $this->crearEmpresa('77900006-6', 'Empresa Module Keys F11');
        $usuario = $this->crearUsuarioConPermisos([], $empresa, ['inventario.reportes']);

        $this->crearProductoConStock($empresa, 'SKU-MODULE-F11', 'Producto Module Key', 7, 700);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/inventario/reportes/stock')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->get('/api/inventario/reportes/stock/exportar-csv')
            ->assertOk();
    }

    private function crearUsuarioConPermisos(array $permisos, ?Empresa $empresa = null, array $moduleKeys = []): User
    {
        $empresa ??= $this->crearEmpresa(
            '779' . random_int(10000, 99999) . '-' . random_int(0, 9),
            'Empresa Test Inventario F11 ' . uniqid()
        );

        $estado = EstadoSuscripcion::firstOrCreate(['nombre' => 'Activa']);

        $rol = Rol::create([
            'nombre' => 'Rol Fase 11 ' . uniqid(),
            'jerarquia' => 50,
            'permisos' => array_values(array_unique($permisos)),
        ]);

        return User::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Usuario Fase 11',
            'email' => 'fase11_' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'rol_id' => $rol->id,
            'estado_suscripcion_id' => $estado->id,
            'module_keys' => $moduleKeys,
        ]);
    }

    private function crearEmpresa(string $rut, string $razonSocial): Empresa
    {
        return Empresa::create([
            'rut' => $rut,
            'razon_social' => $razonSocial,
            'direccion' => 'Santiago',
            'email' => strtolower(str_replace(' ', '_', $razonSocial)) . '@example.com',
            'telefono' => '+56900000000',
        ]);
    }

    private function crearProductoConStock(Empresa $empresa, string $sku, string $nombre, float $stock, float $valor): Producto
    {
        $unidad = UnidadMedida::firstOrCreate(
            ['codigo' => 'UN'],
            [
                'nombre' => 'Unidad',
                'permite_decimal' => false,
                'activo' => true,
            ]
        );

        $bodega = Bodega::create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BOD-' . substr(md5($sku), 0, 8),
            'nombre' => 'Bodega ' . $sku,
            'estado' => 'ACTIVA',
        ]);

        $producto = Producto::create([
            'empresa_id' => $empresa->id,
            'sku' => $sku,
            'nombre' => $nombre,
            'tipo_producto' => 'BIEN',
            'unidad_medida_id' => $unidad->id,
            'metodo_valorizacion' => 'PMP',
            'costo_promedio' => $stock > 0 ? $valor / $stock : 0,
            'precio_venta_neto' => 1000,
            'afecto_iva' => true,
            'stock_minimo' => 1,
            'bodega_defecto_id' => $bodega->id,
            'permite_merma' => false,
            'maneja_lotes' => false,
            'requiere_fecha_vencimiento' => false,
            'activo' => true,
        ]);

        StockProducto::create([
            'empresa_id' => $empresa->id,
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_actual' => $stock,
            'costo_promedio' => $stock > 0 ? $valor / $stock : 0,
            'valor_total' => $valor,
        ]);

        return $producto;
    }
}
