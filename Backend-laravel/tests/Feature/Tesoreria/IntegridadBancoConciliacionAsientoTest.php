<?php

namespace Tests\Feature\Tesoreria;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Tesoreria\Services\BancoService;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\PlanCuenta;
use Exception;

/**
 * Tests de integridad para los bugs corregidos en:
 *   - BancoService::pagarNominaMasiva  (doble pago bloqueado)
 *   - BancoService / ConciliacionService (cuenta no configurada lanza excepción)
 *   - BancoService::vincularMovimientoAAnticipo (doble conciliación bloqueada)
 *   - AsientoContableService::crearAsientoManual (partida doble obligatoria)
 */
class IntegridadBancoConciliacionAsientoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76.111.222-3',
            'razon_social'   => 'Proveedor Integridad',
            'codigo_interno' => 'PI-001',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function crearCuentaBancariaConCuenta(string $codigoContable = '110101'): CuentaBancariaEmpresa
    {
        return CuentaBancariaEmpresa::create([
            'empresa_id'     => $this->empresa->id,
            'banco'          => 'Banco Test',
            'tipo_cuenta'    => 'CORRIENTE',
            'numero_cuenta'  => '99887766',
            'titular'        => 'Test',
            'rut_titular'    => '11.111.111-1',
            'cuenta_contable' => $codigoContable,
        ]);
    }

    private function crearCuentaBancariaSinCuenta(): CuentaBancariaEmpresa
    {
        return CuentaBancariaEmpresa::create([
            'empresa_id'     => $this->empresa->id,
            'banco'          => 'Banco Sin Config',
            'tipo_cuenta'    => 'CORRIENTE',
            'numero_cuenta'  => '11223344',
            'titular'        => 'Test',
            'rut_titular'    => '22.222.222-2',
            'cuenta_contable' => null,
        ]);
    }

    private function crearFactura(string $estado, string $folio): Factura
    {
        static $seq = 80010000;
        return Factura::create([
            'empresa_id'    => $this->empresa->id,
            'proveedor_id'  => $this->proveedor->id,
            'numero_factura'=> $folio,
            'tipo'          => 'COMPRA',
            'codigo_unico'  => ++$seq,
            'fecha_emision' => '2026-06-01',
            'monto_neto'    => 100000,
            'monto_iva'     => 19000,
            'monto_bruto'   => 119000,
            'estado'        => $estado,
        ]);
    }

    private function crearPlanCuentas(): void
    {
        // Cuentas mínimas para que registrarAsiento valide el plan de la empresa.
        $cuentas = [
            ['codigo' => '110101', 'nombre' => 'Banco', 'tipo' => 'ACTIVO'],
            ['codigo' => '352105', 'nombre' => 'Proveedores', 'tipo' => 'PASIVO'],
        ];
        foreach ($cuentas as $c) {
            PlanCuenta::create(array_merge($c, [
                'empresa_id' => $this->empresa->id,
                'imputable'  => true,
                'activo'     => true,
            ]));
        }
    }

    private function crearMovimientoBancario(int $cuentaId, string $estado = 'PENDIENTE'): int
    {
        return DB::table('movimientos_bancarios')->insertGetId([
            'empresa_id'        => $this->empresa->id,
            'cuenta_bancaria_id'=> $cuentaId,
            'fecha'             => '2026-06-01',
            'hora'              => '10:00:00',
            'descripcion'       => 'Movimiento test',
            'cargo'             => 119000,
            'abono'             => 0,
            'estado'            => $estado,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);
    }

    private function crearAnticipo(string $estado = 'PENDIENTE'): int
    {
        return DB::table('anticipos_proveedores')->insertGetId([
            'empresa_id'  => $this->empresa->id,
            'proveedor_id'=> $this->proveedor->id,
            'monto'       => 119000,
            'estado'      => $estado,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    // ─── Fix 1: doble pago bloqueado ──────────────────────────────────────────

    /**
     * Una factura PAGADA incluida en el listado de pagarNominaMasiva
     * debe abortar con excepción antes de modificar ninguna factura.
     */
    public function test_pagar_nomina_masiva_aborta_si_alguna_factura_ya_esta_pagada()
    {
        $this->crearPlanCuentas();
        $cuenta = $this->crearCuentaBancariaConCuenta('110101');

        $facturaOk   = $this->crearFactura('REGISTRADA', 'FAC-OK-001');
        $facturaPaga = $this->crearFactura('PAGADA', 'FAC-PAG-001');

        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/PAGADA|ANULADA/');

        $service->pagarNominaMasiva(
            $this->empresa->id,
            $this->usuario->id,
            [$facturaOk->id, $facturaPaga->id],
            $cuenta->id
        );
    }

    /**
     * La factura OK no debe haber sido modificada si el batch fue abortado.
     */
    public function test_pagar_nomina_masiva_no_modifica_ninguna_factura_si_hay_pagada()
    {
        $this->crearPlanCuentas();
        $cuenta = $this->crearCuentaBancariaConCuenta('110101');

        $facturaOk   = $this->crearFactura('REGISTRADA', 'FAC-OK-002');
        $facturaPaga = $this->crearFactura('PAGADA', 'FAC-PAG-002');

        $service = app(BancoService::class);

        try {
            $service->pagarNominaMasiva(
                $this->empresa->id,
                $this->usuario->id,
                [$facturaOk->id, $facturaPaga->id],
                $cuenta->id
            );
        } catch (Exception $e) {
            // Esperado
        }

        // La factura OK no debe haber cambiado de estado.
        $this->assertEquals('REGISTRADA', $facturaOk->fresh()->estado,
            'La factura REGISTRADA fue modificada pese a que el batch debía abortar');
    }

    /**
     * Una factura ANULADA también debe bloquear el batch.
     */
    public function test_pagar_nomina_masiva_aborta_si_alguna_factura_esta_anulada()
    {
        $this->crearPlanCuentas();
        $cuenta = $this->crearCuentaBancariaConCuenta('110101');

        $facturaOk      = $this->crearFactura('REGISTRADA', 'FAC-OK-003');
        $facturaAnulada = $this->crearFactura('ANULADA', 'FAC-ANU-001');

        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/PAGADA|ANULADA/');

        $service->pagarNominaMasiva(
            $this->empresa->id,
            $this->usuario->id,
            [$facturaOk->id, $facturaAnulada->id],
            $cuenta->id
        );
    }

    // ─── Fix 2: cuenta no configurada lanza excepción ─────────────────────────

    /**
     * Si la CuentaBancariaEmpresa no tiene cuenta_contable configurada,
     * obtenerCuentaContableDeBanco debe lanzar excepción de negocio clara.
     */
    public function test_obtener_cuenta_contable_de_banco_lanza_excepcion_si_no_configurada()
    {
        $cuenta = $this->crearCuentaBancariaSinCuenta();
        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/c.digo contable|cuenta bancaria.*sin|sin.*contable/iu');

        $service->obtenerCuentaContableDeBanco($this->empresa->id, $cuenta->id);
    }

    /**
     * pagarNominaMasiva debe propagar la excepción cuando la cuenta no tiene
     * código contable, en lugar de usar el fallback hardcodeado 110201.
     */
    public function test_pagar_nomina_masiva_lanza_excepcion_si_cuenta_sin_codigo_contable()
    {
        $cuenta = $this->crearCuentaBancariaSinCuenta();
        $factura = $this->crearFactura('REGISTRADA', 'FAC-SC-001');

        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/c.digo contable|cuenta bancaria.*sin|sin.*contable/iu');

        $service->pagarNominaMasiva(
            $this->empresa->id,
            $this->usuario->id,
            [$factura->id],
            $cuenta->id
        );
    }

    // ─── Fix 3: doble conciliación de anticipo bloqueada ──────────────────────

    /**
     * Un movimiento ya CONCILIADO no puede volver a vincularse con un anticipo.
     */
    public function test_vincular_movimiento_anticipo_rechaza_movimiento_ya_conciliado()
    {
        $cuenta     = $this->crearCuentaBancariaConCuenta('110101');
        $movId      = $this->crearMovimientoBancario($cuenta->id, 'CONCILIADO');
        $anticipoId = $this->crearAnticipo('PENDIENTE');

        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/conciliado/i');

        $service->vincularMovimientoAAnticipo($this->empresa->id, $movId, $anticipoId);
    }

    /**
     * Un anticipo que ya fue vinculado (no PENDIENTE) no puede volverse a vincular.
     */
    public function test_vincular_movimiento_anticipo_rechaza_anticipo_no_pendiente()
    {
        $cuenta     = $this->crearCuentaBancariaConCuenta('110101');
        $movId      = $this->crearMovimientoBancario($cuenta->id, 'PENDIENTE');
        $anticipoId = $this->crearAnticipo('PAGADO');

        $service = app(BancoService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/PENDIENTE/i');

        $service->vincularMovimientoAAnticipo($this->empresa->id, $movId, $anticipoId);
    }

    /**
     * Happy path: movimiento PENDIENTE + anticipo PENDIENTE se vinculan correctamente
     * y ambos cambian de estado.
     */
    public function test_vincular_movimiento_anticipo_exitoso_cambia_estados()
    {
        $cuenta     = $this->crearCuentaBancariaConCuenta('110101');
        $movId      = $this->crearMovimientoBancario($cuenta->id, 'PENDIENTE');
        $anticipoId = $this->crearAnticipo('PENDIENTE');

        $service = app(BancoService::class);
        $service->vincularMovimientoAAnticipo($this->empresa->id, $movId, $anticipoId);

        $mov      = DB::table('movimientos_bancarios')->where('id', $movId)->first();
        $anticipo = DB::table('anticipos_proveedores')->where('id', $anticipoId)->first();

        $this->assertEquals('CONCILIADO_ANTICIPO', $mov->estado);
        $this->assertEquals('PAGADO', $anticipo->estado);
        $this->assertEquals($movId, (int) $anticipo->movimiento_id);
    }

    // ─── Fix 4: asiento manual descuadrado rechazado ──────────────────────────

    /**
     * crearAsientoManual con debe != haber debe lanzar excepción de partida doble.
     */
    public function test_crear_asiento_manual_descuadrado_es_rechazado()
    {
        $this->crearPlanCuentas();

        $service = app(AsientoContableService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Partida Doble|Debe|Haber/i');

        $service->crearAsientoManual([
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->usuario->id,
            'fecha'      => '2026-06-01',
            'glosa'      => 'Asiento descuadrado',
            'tipo'       => 'egreso',
            'detalles'   => [
                ['cuenta_contable' => '352105', 'debe' => 100000, 'haber' => 0],
                ['cuenta_contable' => '110101', 'debe' => 0,      'haber' => 50000], // descuadrado!
            ],
        ]);
    }

    /**
     * crearAsientoManual con cuenta que no existe en el plan de la empresa debe fallar.
     */
    public function test_crear_asiento_manual_con_cuenta_inexistente_es_rechazado()
    {
        $this->crearPlanCuentas();

        $service = app(AsientoContableService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/no existe|otra empresa/i');

        $service->crearAsientoManual([
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->usuario->id,
            'fecha'      => '2026-06-01',
            'glosa'      => 'Cuenta inexistente',
            'tipo'       => 'egreso',
            'detalles'   => [
                ['cuenta_contable' => '352105', 'debe' => 100000, 'haber' => 0],
                ['cuenta_contable' => '999999', 'debe' => 0,      'haber' => 100000], // no existe!
            ],
        ]);
    }

    /**
     * crearAsientoManual cuadrado con cuentas válidas debe persistir correctamente.
     */
    public function test_crear_asiento_manual_cuadrado_persiste_correctamente()
    {
        $this->crearPlanCuentas();

        $service = app(AsientoContableService::class);

        $asiento = $service->crearAsientoManual([
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->usuario->id,
            'fecha'      => '2026-06-01',
            'glosa'      => 'Asiento manual correcto',
            'tipo'       => 'egreso',
            'detalles'   => [
                ['cuenta_contable' => '352105', 'debe' => 100000, 'haber' => 0],
                ['cuenta_contable' => '110101', 'debe' => 0,      'haber' => 100000],
            ],
        ]);

        $this->assertNotNull($asiento->id);
        $this->assertEquals(2, $asiento->detalles()->count());

        $totalDebe  = $asiento->detalles()->sum('debe');
        $totalHaber = $asiento->detalles()->sum('haber');
        $this->assertEquals($totalDebe, $totalHaber);
    }
}
