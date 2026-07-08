<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Laravel\Sanctum\Sanctum;

/**
 * Regresión (contraparte de AnticipoClienteConciliacionTest, rama egreso):
 * un anticipo a proveedor autogenerado en Mesa de Conciliación no guardaba
 * el asiento_id que lo originó -- al anular ese asiento quedaba PAGADO para
 * siempre, con el movimiento ya liberado y disponible para re-conciliarse.
 */
class AnticipoProveedorConciliacionAsientoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;
    protected $cuenta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '76.222.333-4',
            'razon_social' => 'Proveedor Anticipo Test',
            'codigo_interno' => 'PAT-001',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresa->id,
            'banco' => 'Banco Test',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '55443322',
            'titular' => 'Test',
            'rut_titular' => '11.111.111-1',
            'cuenta_contable' => '110101',
        ]);

        foreach ([
            ['codigo' => '110101', 'nombre' => 'Banco', 'tipo' => 'ACTIVO'],
            ['codigo' => '352105', 'nombre' => 'Cuentas por Pagar', 'tipo' => 'PASIVO'],
            ['codigo' => '110205', 'nombre' => 'Anticipos a Proveedores', 'tipo' => 'ACTIVO'],
        ] as $c) {
            PlanCuenta::create(array_merge($c, [
                'empresa_id' => $this->empresa->id,
                'imputable' => true,
                'activo' => true,
            ]));
        }
    }

    private function crearMovimientoEgreso(float $cargo): int
    {
        return DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'cuenta_bancaria_id' => $this->cuenta->id,
            'fecha' => '2026-06-01',
            'descripcion' => 'Pago a proveedor',
            'cargo' => $cargo,
            'abono' => 0,
            'estado' => 'PENDIENTE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_anticipo_a_proveedor_queda_vinculado_al_asiento_que_lo_origino()
    {
        $movId = $this->crearMovimientoEgreso(40000);

        Sanctum::actingAs($this->usuario);
        $response = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movId,
            'facturas_ids' => [],
            'entidad_id' => $this->proveedor->id,
        ]);

        $response->assertOk();
        $asientoId = DB::table('asientos_contables')
            ->where('numero_comprobante', $response->json('asiento.numero_comprobante'))
            ->value('id');

        $this->assertDatabaseHas('anticipos_proveedores', [
            'proveedor_id' => $this->proveedor->id,
            'asiento_id' => $asientoId,
        ]);
    }

    public function test_anular_el_asiento_libera_el_anticipo_a_proveedor()
    {
        $movId = $this->crearMovimientoEgreso(60000);

        Sanctum::actingAs($this->usuario);
        $conciliacion = $this->postJson('/api/banco/movimientos/conciliar-facturas', [
            'movimiento_id' => $movId,
            'facturas_ids' => [],
            'entidad_id' => $this->proveedor->id,
        ])->assertOk();

        $asientoId = DB::table('asientos_contables')
            ->where('numero_comprobante', $conciliacion->json('asiento.numero_comprobante'))
            ->value('id');

        $this->postJson('/api/anulacion/anular', [
            'tipo_documento' => 'ASIENTO',
            'documento_id' => $asientoId,
            'motivo' => 'Prueba de regresión',
            'fecha_anulacion' => now()->format('Y-m-d'),
        ])->assertOk();

        $this->assertDatabaseHas('anticipos_proveedores', [
            'proveedor_id' => $this->proveedor->id,
            'estado' => 'ANULADO',
            'asiento_id' => null,
            'movimiento_id' => null,
        ]);
        $this->assertDatabaseHas('movimientos_bancarios', [
            'id' => $movId,
            'estado' => 'PENDIENTE',
        ]);
    }
}
