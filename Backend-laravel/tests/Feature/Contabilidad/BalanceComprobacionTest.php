<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;

class BalanceComprobacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected string $ruta = '/api/contabilidad/reportes/balance-comprobacion';

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = Empresa::create([
            'rut'         => '76.543.210-9',
            'razon_social' => 'Tenri Test SpA',
        ]);

        $this->usuario = User::create([
            'nombre'               => 'Contador Balance',
            'email'                => 'balance@tenri.cl',
            'password'             => bcrypt('123'),
            'empresa_id'           => $this->empresa->id,
            'rol_id'               => $this->rolContador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers privados para reducir repetición en cada test
    // -----------------------------------------------------------------------

    /** Crea una cuenta contable para la empresa del test. */
    private function crearCuenta(string $codigo, string $nombre, string $tipo): PlanCuenta
    {
        return PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo'     => $codigo,
            'nombre'     => $nombre,
            'tipo'       => $tipo,
            'imputable'  => true,
            'activo'     => true,
        ]);
    }

    /**
     * Crea un asiento balanceado (debe == haber) con dos líneas de detalle.
     * Devuelve el AsientoContable creado.
     */
    private function crearAsientoBalanceado(
        string $codigoDebe,
        float $montoDebe,
        string $codigoHaber,
        float $montoHaber,
        string $fecha = '2026-05-15',
        string $estado = 'MAYORIZADO'
    ): AsientoContable {
        static $seq = 0;
        $seq++;

        $asiento = AsientoContable::create([
            'empresa_id'        => $this->empresa->id,
            'numero_comprobante' => 'BC-' . str_pad($seq, 4, '0', STR_PAD_LEFT),
            'fecha'             => $fecha,
            'glosa'             => 'Asiento balance test ' . $seq,
            'estado'            => $estado,
        ]);

        DetalleAsiento::create([
            'asiento_id'      => $asiento->id,
            'cuenta_contable' => $codigoDebe,
            'debe'            => $montoDebe,
            'haber'           => 0,
            'fecha'           => $fecha,
            'tipo_operacion'  => 'DEBE',
        ]);

        DetalleAsiento::create([
            'asiento_id'      => $asiento->id,
            'cuenta_contable' => $codigoHaber,
            'debe'            => 0,
            'haber'           => $montoHaber,
            'fecha'           => $fecha,
            'tipo_operacion'  => 'HABER',
        ]);

        return $asiento;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * El total de debe del balance debe ser igual al total de haber,
     * porque cada asiento insertado está cuadrado (partida doble).
     */
    public function test_balance_cuadra_debe_igual_haber(): void
    {
        $caja    = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $capital = $this->crearCuenta('3001', 'Capital', 'PATRIMONIO');
        $banco   = $this->crearCuenta('1102', 'Banco', 'ACTIVO');
        $ventas  = $this->crearCuenta('4001', 'Ventas', 'INGRESO');

        $this->crearAsientoBalanceado($caja->codigo, 1000000, $capital->codigo, 1000000);
        $this->crearAsientoBalanceado($banco->codigo, 500000, $ventas->codigo, 500000);

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200)->assertJsonPath('success', true);

        $totales = $response->json('data.totales');
        $this->assertEquals($totales['debe'], $totales['haber'], 'El total Debe no coincide con el total Haber.');
    }

    /**
     * Tres asientos que tocan la misma cuenta Caja deben producir
     * exactamente una fila para esa cuenta con la suma acumulada.
     */
    public function test_balance_agrupa_movimientos_por_cuenta(): void
    {
        $caja    = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $capital = $this->crearCuenta('3001', 'Capital', 'PATRIMONIO');

        $this->crearAsientoBalanceado($caja->codigo, 100, $capital->codigo, 100, '2026-05-01');
        $this->crearAsientoBalanceado($caja->codigo, 200, $capital->codigo, 200, '2026-05-05');
        $this->crearAsientoBalanceado($caja->codigo, 300, $capital->codigo, 300, '2026-05-10');

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200);

        $cuentas = $response->json('data.cuentas');

        // Solo dos cuentas en el balance: Caja y Capital
        $filasCaja = array_values(array_filter($cuentas, fn($c) => $c['codigo'] === $caja->codigo));
        $this->assertCount(1, $filasCaja, 'Caja debe aparecer en una sola fila agrupada.');
        $this->assertEquals(600.0, $filasCaja[0]['debe'], 'La suma acumulada de Debe en Caja debe ser 600.');
        $this->assertEquals(0.0, $filasCaja[0]['haber']);
    }

    /**
     * Cuenta ACTIVO con debe > haber → saldo_deudor > 0, saldo_acreedor = 0.
     * Cuenta PASIVO con haber > debe → saldo_deudor = 0, saldo_acreedor > 0.
     */
    public function test_saldo_deudor_asignado_por_signo_real(): void
    {
        $caja   = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $pasivo = $this->crearCuenta('2101', 'Préstamo Bancario', 'PASIVO');

        // Asiento: CAJA DEBE 800 / PASIVO HABER 800
        $this->crearAsientoBalanceado($caja->codigo, 800, $pasivo->codigo, 800);

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200);

        $cuentas = $response->json('data.cuentas');

        $filaCaja   = collect($cuentas)->firstWhere('codigo', $caja->codigo);
        $filaPasivo = collect($cuentas)->firstWhere('codigo', $pasivo->codigo);

        // Caja: debe > haber → saldo deudor
        $this->assertGreaterThan(0, $filaCaja['saldo_deudor']);
        $this->assertEquals(0.0, $filaCaja['saldo_acreedor']);

        // Pasivo: haber > debe → saldo acreedor
        $this->assertEquals(0.0, $filaPasivo['saldo_deudor']);
        $this->assertGreaterThan(0, $filaPasivo['saldo_acreedor']);
    }

    /**
     * Con filtro=1 (default) los asientos ANULADOS no deben aparecer
     * en el balance; solo el asiento MAYORIZADO es visible.
     */
    public function test_respeta_filtro_estado_asiento(): void
    {
        $caja    = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $capital = $this->crearCuenta('3001', 'Capital', 'PATRIMONIO');

        // Asiento válido: 5000
        $this->crearAsientoBalanceado($caja->codigo, 5000, $capital->codigo, 5000, '2026-05-10', 'MAYORIZADO');

        // Asiento ANULADO con monto distinto: 99000 — no debe sumarse
        $this->crearAsientoBalanceado($caja->codigo, 99000, $capital->codigo, 99000, '2026-05-12', 'ANULADO');

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31&filtro=1'
        );

        $response->assertStatus(200);

        $cuentas = $response->json('data.cuentas');
        $filaCaja = collect($cuentas)->firstWhere('codigo', $caja->codigo);

        // Solo el asiento MAYORIZADO (5000) debe contarse
        $this->assertEquals(5000.0, $filaCaja['debe'], 'El asiento ANULADO no debe incluirse con filtro=1.');
    }

    /**
     * Con asientos balanceados, la suma de saldo_deudor de todas las cuentas
     * debe ser aproximadamente igual a la suma de saldo_acreedor.
     */
    public function test_totales_saldo_deudor_igual_saldo_acreedor(): void
    {
        $caja    = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $capital = $this->crearCuenta('3001', 'Capital', 'PATRIMONIO');
        $banco   = $this->crearCuenta('1102', 'Banco', 'ACTIVO');
        $ventas  = $this->crearCuenta('4001', 'Ventas', 'INGRESO');

        $this->crearAsientoBalanceado($caja->codigo, 2000, $capital->codigo, 2000);
        $this->crearAsientoBalanceado($banco->codigo, 1500, $ventas->codigo, 1500);

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200);

        $totales = $response->json('data.totales');

        $this->assertEqualsWithDelta(
            $totales['saldo_deudor'],
            $totales['saldo_acreedor'],
            0.01,
            'Con asientos balanceados, total saldo_deudor debe igualar total saldo_acreedor.'
        );
    }

    /**
     * GET sin fecha_inicio debe devolver 422 con error de validación.
     */
    public function test_endpoint_valida_parametros_requeridos(): void
    {
        $response = $this->actingAs($this->usuario)->getJson($this->ruta);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['fecha_inicio', 'fecha_fin']);
    }

    /**
     * Asientos creados en empresa B no deben aparecer en el balance
     * del usuario que pertenece a empresa A.
     */
    public function test_multitenant_no_cruza_empresas(): void
    {
        // Empresa A tiene su propia cuenta y asiento
        $cajaA    = $this->crearCuenta('1101', 'Caja A', 'ACTIVO');
        $capitalA = $this->crearCuenta('3001', 'Capital A', 'PATRIMONIO');
        $this->crearAsientoBalanceado($cajaA->codigo, 1000, $capitalA->codigo, 1000);

        // Empresa B con sus propias cuentas y asiento
        $empresaB = Empresa::create(['rut' => '88.888.888-8', 'razon_social' => 'Empresa B']);

        $cajaB = PlanCuenta::create([
            'empresa_id' => $empresaB->id,
            'codigo'     => '1101',
            'nombre'     => 'Caja B',
            'tipo'       => 'ACTIVO',
            'imputable'  => true,
            'activo'     => true,
        ]);
        $capitalB = PlanCuenta::create([
            'empresa_id' => $empresaB->id,
            'codigo'     => '3001',
            'nombre'     => 'Capital B',
            'tipo'       => 'PATRIMONIO',
            'imputable'  => true,
            'activo'     => true,
        ]);

        $asientoB = AsientoContable::create([
            'empresa_id'        => $empresaB->id,
            'numero_comprobante' => 'BC-B-0001',
            'fecha'             => '2026-05-15',
            'glosa'             => 'Asiento empresa B',
            'estado'            => 'MAYORIZADO',
        ]);
        DetalleAsiento::create([
            'asiento_id'      => $asientoB->id,
            'cuenta_contable' => $cajaB->codigo,
            'debe'            => 999999,
            'haber'           => 0,
            'fecha'           => '2026-05-15',
            'tipo_operacion'  => 'DEBE',
        ]);
        DetalleAsiento::create([
            'asiento_id'      => $asientoB->id,
            'cuenta_contable' => $capitalB->codigo,
            'debe'            => 0,
            'haber'           => 999999,
            'fecha'           => '2026-05-15',
            'tipo_operacion'  => 'HABER',
        ]);

        // El usuario de empresa A consulta su balance
        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200);

        // El total debe de empresa A es 1000, no 1000 + 999999
        $totales = $response->json('data.totales');
        $this->assertEquals(1000.0, $totales['debe'], 'El balance de empresa A no debe incluir datos de empresa B.');
    }

    /**
     * Con fecha_fin anterior a fecha_inicio debe devolver 422.
     */
    public function test_endpoint_rechaza_rango_de_fechas_invertido(): void
    {
        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-12-31&fecha_fin=2026-01-01'
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_fin']);
    }

    /**
     * Sin autenticación el endpoint devuelve 401.
     */
    public function test_endpoint_requiere_autenticacion(): void
    {
        $response = $this->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(401);
    }

    /**
     * El balance devuelve la estructura JSON correcta: periodo, cuentas, totales.
     */
    public function test_estructura_json_de_respuesta_es_valida(): void
    {
        $caja    = $this->crearCuenta('1101', 'Caja', 'ACTIVO');
        $capital = $this->crearCuenta('3001', 'Capital', 'PATRIMONIO');
        $this->crearAsientoBalanceado($caja->codigo, 1000, $capital->codigo, 1000);

        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-05-01&fecha_fin=2026-05-31'
        );

        $response->assertStatus(200)->assertJsonStructure([
            'success',
            'data' => [
                'periodo' => ['inicio', 'fin'],
                'cuentas' => [
                    '*' => ['codigo', 'nombre', 'tipo', 'debe', 'haber', 'saldo_deudor', 'saldo_acreedor'],
                ],
                'totales' => ['debe', 'haber', 'saldo_deudor', 'saldo_acreedor'],
            ],
        ]);
    }

    /**
     * Sin movimientos en el período, el balance devuelve cuentas vacías
     * y totales en cero, sin error.
     */
    public function test_periodo_sin_movimientos_devuelve_balance_vacio(): void
    {
        $response = $this->actingAs($this->usuario)->getJson(
            $this->ruta . '?fecha_inicio=2026-01-01&fecha_fin=2026-01-31'
        );

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.cuentas'));
        $this->assertEquals(0.0, $response->json('data.totales.debe'));
        $this->assertEquals(0.0, $response->json('data.totales.haber'));
    }
}
