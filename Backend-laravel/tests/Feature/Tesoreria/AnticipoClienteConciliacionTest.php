<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Laravel\Sanctum\Sanctum;

/**
 * Regresión: un cobro de más (anticipo de cliente) generado en Mesa de
 * Conciliación solo dejaba el asiento contable suelto, sin cliente_id -- no
 * existía tabla anticipos_clientes (a diferencia de anticipos_proveedores),
 * así que Visor 360 Cliente nunca podía mostrar los cobros anticipados.
 */
class AnticipoClienteConciliacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cliente;
    protected $cuenta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '77.111.222-3',
            'razon_social' => 'Cliente Anticipo Test',
            'estado' => 'ACTIVO',
        ]);

        $this->cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresa->id,
            'banco' => 'Banco Test',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '99887766',
            'titular' => 'Test',
            'rut_titular' => '11.111.111-1',
            'cuenta_contable' => '110101',
        ]);

        foreach ([
            ['codigo' => '110101', 'nombre' => 'Banco', 'tipo' => 'ACTIVO'],
            ['codigo' => '152005', 'nombre' => 'Cuentas por Cobrar', 'tipo' => 'ACTIVO'],
            ['codigo' => '210205', 'nombre' => 'Anticipos de Clientes', 'tipo' => 'PASIVO'],
        ] as $c) {
            PlanCuenta::create(array_merge($c, [
                'empresa_id' => $this->empresa->id,
                'imputable' => true,
                'activo' => true,
            ]));
        }
    }

    private function crearMovimientoIngreso(float $abono): int
    {
        return DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'fecha' => '2026-06-01',
            'descripcion' => 'Cobro cliente',
            'cargo' => 0,
            'abono' => $abono,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_conciliar_sin_facturas_con_entidad_id_registra_anticipo_de_cliente()
    {
        $movId = $this->crearMovimientoIngreso(50000);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movId,
            'facturas_ids' => [],
            'entidad_id' => $this->cliente->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('anticipos_clientes', [
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'monto' => 50000,
            'estado' => 'PAGADO',
            'movimiento_id' => $movId,
        ]);
    }

    public function test_ficha_del_cliente_incluye_el_anticipo_registrado()
    {
        $movId = $this->crearMovimientoIngreso(75000);

        Sanctum::actingAs($this->usuario);
        $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movId,
            'facturas_ids' => [],
            'entidad_id' => $this->cliente->id,
        ])->assertOk();

        $response = $this->getJson("/api/clientes/ficha/{$this->cliente->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.anticipos');
        $response->assertJsonPath('data.anticipos.0.monto', '75000.00');
        $response->assertJsonPath('data.anticipos.0.estado', 'PAGADO');
    }

    public function test_no_puede_registrar_anticipo_para_cliente_de_otra_empresa()
    {
        $empresaB = $this->crearEmpresa();
        $clienteB = Cliente::create([
            'empresa_id' => $empresaB->id,
            'rut' => '88.222.333-4',
            'razon_social' => 'Cliente Ajeno',
            'estado' => 'ACTIVO',
        ]);

        $movId = $this->crearMovimientoIngreso(30000);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movId,
            'facturas_ids' => [],
            'entidad_id' => $clienteB->id,
        ]);

        $this->assertNotContains($response->getStatusCode(), [200, 201]);
        $this->assertDatabaseMissing('anticipos_clientes', ['cliente_id' => $clienteB->id]);
    }
}
