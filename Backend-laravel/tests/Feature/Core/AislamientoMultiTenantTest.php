<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\CentroCosto;

class AislamientoMultiTenantTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $empresaB;
    protected $usuarioA;
    protected $usuarioB;
    protected $clienteB;
    protected $proveedorB;
    protected $cuentaB;
    protected $centroCostoB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresaA, $this->usuarioA] = $this->crearEmpresaConAdmin([
            'razon_social' => 'Empresa A',
        ], ['email' => 'admin-a@test.cl']);

        [$this->empresaB, $this->usuarioB] = $this->crearEmpresaConAdmin([
            'razon_social' => 'Empresa B',
        ], ['email' => 'admin-b@test.cl']);

        $this->clienteB = Cliente::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Cliente Privado B',
        ]);

        $this->proveedorB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => '22.222.222-2',
            'razon_social' => 'Proveedor Privado B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->cuentaB = PlanCuenta::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => '999999',
            'nombre' => 'Cuenta secreta B',
            'tipo' => 'GASTO',
            'imputable' => true,
            'activo' => true,
        ]);

        $this->centroCostoB = CentroCosto::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => 'CC-B',
            'nombre' => 'Centro de Costo B',
            'activo' => true,
        ]);
    }

    public function test_usuario_a_no_puede_ver_lista_de_clientes_de_empresa_b()
    {
        $response = $this->actingAs($this->usuarioA)->getJson('/api/clientes');

        $response->assertStatus(200);
        $body = $response->json();
        $clientes = $body['data'] ?? $body;

        if (is_array($clientes)) {
            foreach ($clientes as $cliente) {
                $this->assertNotEquals(
                    $this->clienteB->id,
                    $cliente['id'] ?? null,
                    'Filtracion: cliente de empresa B aparecio en respuesta a empresa A'
                );
            }
        }
    }

    public function test_usuario_a_no_puede_ver_cliente_especifico_de_empresa_b_por_idor()
    {
        $response = $this->actingAs($this->usuarioA)->getJson("/api/clientes/{$this->clienteB->id}");
        $response->assertStatus(404);
    }

    public function test_usuario_a_no_puede_ver_proveedor_de_empresa_b_por_idor()
    {
        $response = $this->actingAs($this->usuarioA)->getJson("/api/proveedores/{$this->proveedorB->id}");

        $statusCode = $response->getStatusCode();
        $this->assertNotEquals(
            200,
            $statusCode,
            'IDOR CRITICO: usuario A pudo VER proveedor de empresa B'
        );

        $this->assertContains($statusCode, [403, 404, 405]);
    }

    public function test_usuario_a_no_puede_listar_centros_costo_de_empresa_b()
    {
        $response = $this->actingAs($this->usuarioA)->getJson('/api/centros-costo');

        if (in_array($response->getStatusCode(), [404, 405])) {
            $this->markTestSkipped('Ruta /api/centros-costo no expuesta como GET.');
        }

        $response->assertStatus(200);
        $body = $response->json();
        $centros = $body['data'] ?? $body;
        $this->assertIsArray($centros);

        $idsExpuestos = array_map(fn($c) => $c['id'] ?? null, $centros);
        $this->assertNotContains(
            $this->centroCostoB->id,
            $idsExpuestos,
            'Filtracion: centro de costo de empresa B aparecio en lista de empresa A'
        );
    }

    public function test_usuario_a_no_puede_crear_factura_referenciando_proveedor_de_empresa_b()
    {
        $response = $this->actingAs($this->usuarioA)->postJson('/api/facturas', [
            'proveedor_id' => $this->proveedorB->id,
            'numero_factura' => 'F-IDOR-001',
            'tipo' => 'COMPRA',
            'fecha_emision' => '2026-05-01',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            [200, 201],
            'IDOR: usuario A pudo crear factura usando proveedor de empresa B'
        );
    }

    public function test_token_de_empresa_a_no_puede_modificar_cuenta_contable_de_empresa_b()
    {
        $response = $this->actingAs($this->usuarioA)->putJson("/api/plan-cuentas/{$this->cuentaB->id}", [
            'nombre' => 'Cuenta hackeada',
            'tipo' => 'GASTO',
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404, 422]);

        $cuentaActualizada = PlanCuenta::find($this->cuentaB->id);
        $this->assertEquals('Cuenta secreta B', $cuentaActualizada->nombre);
    }

    public function test_usuario_a_no_puede_eliminar_centro_costo_de_empresa_b()
    {
        $response = $this->actingAs($this->usuarioA)->deleteJson("/api/centros-costo/{$this->centroCostoB->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertNotNull(
            CentroCosto::find($this->centroCostoB->id),
            'IDOR: usuario A pudo eliminar centro de costo de empresa B'
        );
    }

    public function test_busqueda_global_de_facturas_no_filtra_facturas_de_otra_empresa()
    {
        $facturaB = Factura::create([
            'empresa_id' => $this->empresaB->id,
            'proveedor_id' => $this->proveedorB->id,
            'numero_factura' => 'SECRETA-B',
            'tipo' => 'COMPRA',
            'codigo_unico' => 88888888,
            'fecha_emision' => '2026-04-15',
            'monto_neto' => 500000,
            'monto_iva' => 95000,
            'monto_bruto' => 595000,
            'estado' => 'REGISTRADA',
        ]);

        $response = $this->actingAs($this->usuarioA)->getJson('/api/facturas?search=SECRETA');

        if (in_array($response->getStatusCode(), [404, 405])) {
            $this->markTestSkipped('Endpoint de busqueda no expuesto.');
        }

        $response->assertStatus(200);
        $body = $response->json();
        $facturas = $body['data'] ?? $body;
        $this->assertIsArray($facturas);

        $idsExpuestos = array_map(fn($f) => $f['id'] ?? null, $facturas);
        $this->assertNotContains(
            $facturaB->id,
            $idsExpuestos,
            'Filtracion CRITICA: busqueda global expuso factura de empresa B'
        );
    }
}
