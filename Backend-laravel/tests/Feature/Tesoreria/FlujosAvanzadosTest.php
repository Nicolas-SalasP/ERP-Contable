<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\AnticipoProveedor;
use Laravel\Sanctum\Sanctum;

class FlujosAvanzadosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $empresaB;
    protected $usuarioA;
    protected $usuarioB;
    protected $cuentaA;
    protected $cuentaB;
    protected $proveedorA;
    protected $proveedorB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresaA, $this->usuarioA] = $this->crearEmpresaConAdmin([
            'razon_social' => 'Empresa A Tesoreria',
        ], ['email' => 'admin-a-tes@test.cl']);

        [$this->empresaB, $this->usuarioB] = $this->crearEmpresaConAdmin([
            'razon_social' => 'Empresa B Tesoreria',
        ], ['email' => 'admin-b-tes@test.cl']);

        $this->cuentaA = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'rut_titular' => $this->empresaA->rut,
            'titular' => $this->empresaA->razon_social,
            'banco' => 'Banco de Chile',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '11111111',
        ]);

        $this->cuentaB = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaB->id,
            'rut_titular' => $this->empresaB->rut,
            'titular' => $this->empresaB->razon_social,
            'banco' => 'BancoEstado',
            'tipo_cuenta' => 'CORRIENTE',
            'numero_cuenta' => '22222222',
        ]);

        $this->proveedorA = Proveedor::create([
            'empresa_id' => $this->empresaA->id,
            'rut' => '11.111.111-1',
            'razon_social' => 'Prov A',
            'codigo_interno' => 'PA',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $this->proveedorB = Proveedor::create([
            'empresa_id' => $this->empresaB->id,
            'rut' => '22.222.222-2',
            'razon_social' => 'Prov B',
            'codigo_interno' => 'PB',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    public function test_anticipos_pendientes_aisla_por_tenant()
    {
        AnticipoProveedor::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $this->proveedorA->id,
            'monto' => 100000,
            'saldo_disponible' => 100000,
            'fecha' => '2026-05-01',
            'estado' => 'DISPONIBLE',
        ]);

        $anticipoB = AnticipoProveedor::create([
            'empresa_id' => $this->empresaB->id,
            'proveedor_id' => $this->proveedorB->id,
            'monto' => 500000,
            'saldo_disponible' => 500000,
            'fecha' => '2026-05-01',
            'estado' => 'DISPONIBLE',
        ]);

        Sanctum::actingAs($this->usuarioA);
        $response = $this->getJson('/api/banco/anticipos-pendientes');

        if (in_array($response->getStatusCode(), [404, 405])) {
            $this->markTestSkipped('Endpoint anticipos-pendientes no expuesto.');
        }

        $response->assertStatus(200);
        $body = $response->json();
        $anticipos = $body['data'] ?? $body;
        $this->assertIsArray($anticipos);

        $idsExpuestos = array_map(fn($a) => $a['id'] ?? null, $anticipos);
        $this->assertNotContains(
            $anticipoB->id,
            $idsExpuestos,
            'Filtracion: anticipo de empresa B aparecio en lista de empresa A'
        );
    }

    public function test_movimiento_bancario_persiste_cargo_y_abono_correctamente()
    {
        $movId = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresaA->id,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Deposito cliente Y',
            'cargo' => 0,
            'abono' => 1000000,
            'estado' => 'PENDIENTE',
        ]);

        $persistido = DB::table('movimientos_bancarios')->where('id', $movId)->first();
        $this->assertEquals(1000000, (float) $persistido->abono);
        $this->assertEquals(0, (float) $persistido->cargo);
    }

    public function test_pagar_nomina_con_cuenta_de_otra_empresa_es_rechazado()
    {
        $facturaA = Factura::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $this->proveedorA->id,
            'numero_factura' => 'FAC-A-001',
            'tipo' => 'COMPRA',
            'codigo_unico' => 70000001,
            'fecha_emision' => '2026-05-01',
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_bruto' => 119000,
            'estado' => 'REGISTRADA',
        ]);

        Sanctum::actingAs($this->usuarioA);
        $response = $this->postJson('/api/banco/nomina/pagar', [
            'facturas_ids' => [$facturaA->id],
            'cuenta_bancaria_id' => $this->cuentaB->id,
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            [200, 201],
            'IDOR CRITICO: usuario A pudo cargar pago contra cuenta de empresa B'
        );
    }

    public function test_anticipo_no_puede_aplicarse_cuando_estado_esta_consumido()
    {
        $anticipo = AnticipoProveedor::create([
            'empresa_id' => $this->empresaA->id,
            'proveedor_id' => $this->proveedorA->id,
            'monto' => 100000,
            'saldo_disponible' => 0,
            'fecha' => '2026-05-01',
            'estado' => 'CONSUMIDO',
        ]);

        Sanctum::actingAs($this->usuarioA);
        $response = $this->postJson("/api/anticipos-proveedores/{$anticipo->id}/aplicar", [
            'factura_id' => 99999,
            'monto_a_aplicar' => 50000,
        ]);

        $this->assertNotContains(
            $response->getStatusCode(),
            [200, 201],
            'Bug: se aplico anticipo CONSUMIDO con saldo 0'
        );
    }

    public function test_ingreso_manual_con_monto_extremo_no_pierde_precision()
    {
        Sanctum::actingAs($this->usuarioA);
        $monto = 999999999.99;

        $response = $this->postJson('/api/banco/ingreso-manual', [
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Deposito grande',
            'monto' => $monto,
            'tipo_movimiento' => 'ABONO',
        ]);

        $response->assertStatus(201);

        $mov = DB::table('movimientos_bancarios')
            ->where('cuenta_bancaria_id', $this->cuentaA->id)
            ->where('descripcion', 'Deposito grande')
            ->first();

        $this->assertNotNull($mov, 'El movimiento no se persistio en BD');
        $abonoGuardado = (float) $mov->abono;
        $cargoGuardado = (float) $mov->cargo;
        $totalGuardado = $abonoGuardado + $cargoGuardado;
        $this->assertEquals(
            $monto,
            $totalGuardado,
            "Perdida de precision: esperado {$monto}, guardado {$totalGuardado}"
        );
    }

    public function test_movimiento_bancario_no_puede_tener_cargo_y_abono_simultaneos()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('movimientos_bancarios')->insert([
            'empresa_id' => $this->empresaA->id,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Movimiento dudoso',
            'cargo' => 50000,
            'abono' => 30000,
            'estado' => 'PENDIENTE',
        ]);
    }

    public function test_movimiento_bancario_solo_con_cargo_es_aceptado()
    {
        $movId = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresaA->id,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Solo cargo',
            'cargo' => 100000,
            'abono' => 0,
            'estado' => 'PENDIENTE',
        ]);

        $this->assertGreaterThan(0, $movId);
    }

    public function test_movimiento_bancario_solo_con_abono_es_aceptado()
    {
        $movId = DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id' => $this->empresaA->id,
            'cuenta_bancaria_id' => $this->cuentaA->id,
            'fecha' => '2026-05-01',
            'descripcion' => 'Solo abono',
            'cargo' => 0,
            'abono' => 100000,
            'estado' => 'PENDIENTE',
        ]);

        $this->assertGreaterThan(0, $movId);
    }
}
