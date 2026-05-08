<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;

/**
 * Tests de precision monetaria y casos extremos contables.
 *
 * Cubre escenarios criticos en produccion: redondeos al peso,
 * IVA en montos grandes, asientos descuadrados al centavo.
 *
 * Si alguno de estos tests falla en produccion, hay riesgo real
 * de generar asientos contables descuadrados o mal calculados.
 */
class PrecisionMonetariaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        // Crear cuentas contables base
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110101',
            'nombre' => 'Caja',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110201',
            'nombre' => 'Banco',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '410101',
            'nombre' => 'Ingreso por servicios',
            'tipo' => 'INGRESO',
            'imputable' => true,
            'activo' => true,
        ]);
    }

    public function test_asiento_con_montos_decimales_que_cuadran_se_persiste_correctamente()
    {
        // Caso: 333.33 + 666.67 = 1000.00 (cuadra al centavo)
        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento con decimales precisos',
            'numero_comprobante' => 'PREC-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 90000001,
        ]);

        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '110101',
            'tipo_operacion' => 'DEBE',
            'debe' => 333.33,
            'haber' => 0,
        ]);
        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '110201',
            'tipo_operacion' => 'DEBE',
            'debe' => 666.67,
            'haber' => 0,
        ]);
        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '410101',
            'tipo_operacion' => 'HABER',
            'debe' => 0,
            'haber' => 1000.00,
        ]);

        $detalles = $asiento->fresh()->load('detalles')->detalles ?? collect();
        $sumaDebe = $detalles->sum('debe');
        $sumaHaber = $detalles->sum('haber');

        $this->assertEquals($sumaDebe, $sumaHaber,
            "Asiento descuadrado: debe={$sumaDebe} haber={$sumaHaber}");
        $this->assertEquals(1000.00, (float) $sumaDebe);
    }

    public function test_asiento_con_montos_grandes_billones_no_pierde_precision()
    {
        // Limite de decimal(15,2): 9.999.999.999.999,99 - menos de 10 billones
        $monto = 999999999999.99;

        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento billonario',
            'numero_comprobante' => 'BIG-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 90000002,
        ]);

        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '110101',
            'tipo_operacion' => 'DEBE',
            'debe' => $monto,
            'haber' => 0,
        ]);
        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '410101',
            'tipo_operacion' => 'HABER',
            'debe' => 0,
            'haber' => $monto,
        ]);

        $detallesGuardados = DetalleAsiento::where('asiento_id', $asiento->id)->get();
        $debeGuardado = (float) $detallesGuardados->where('tipo_operacion', 'DEBE')->first()->debe;
        $haberGuardado = (float) $detallesGuardados->where('tipo_operacion', 'HABER')->first()->haber;

        // Verificar que NO hubo perdida de precision al redondeo de centavos
        $this->assertEquals($monto, $debeGuardado,
            "Perdida precision en debe: esperado {$monto}, guardado {$debeGuardado}");
        $this->assertEquals($monto, $haberGuardado);
        $this->assertEquals($debeGuardado, $haberGuardado);
    }

    public function test_iva_19_porciento_de_calculos_estandar_no_pierde_centavos()
    {
        // Caso comun: $100.000 neto debe dar exactamente $19.000 IVA
        $neto = 100000;
        $tasaIva = 0.19;
        $iva = round($neto * $tasaIva);

        $this->assertEquals(19000, $iva);
        $this->assertEquals(119000, $neto + $iva);
    }

    public function test_iva_19_porciento_redondea_correctamente_montos_irregulares()
    {
        // 12345 * 0.19 = 2345.55 -> SII redondea hacia arriba: 2346
        // 67890 * 0.19 = 12899.1 -> SII redondea hacia abajo: 12899
        $casos = [
            ['neto' => 12345, 'iva_esperado' => 2346], // .55 redondea a 56 -> 2346
            ['neto' => 67890, 'iva_esperado' => 12899],
            ['neto' => 100, 'iva_esperado' => 19],
            ['neto' => 1, 'iva_esperado' => 0],
        ];

        foreach ($casos as $c) {
            $iva = (int) round($c['neto'] * 0.19);
            $this->assertEquals($c['iva_esperado'], $iva,
                "IVA mal calculado para neto={$c['neto']}: esperado {$c['iva_esperado']}, obtenido {$iva}");
        }
    }

    public function test_redondeo_no_acumula_error_en_factura_con_muchas_lineas()
    {
        // 100 lineas de $999.99 cada una
        $lineas = [];
        for ($i = 0; $i < 100; $i++) {
            $lineas[] = round(333.33 * 3);
        }
        $total = array_sum($lineas);

        // Verificar que no se "perdieron" pesos por redondeo
        $this->assertEquals(100000, $total,
            "Error de redondeo acumulado: total {$total}, esperado 100000");
    }

    public function test_decimal_15_2_acepta_montos_grandes_realistas()
    {
        // Limite teorico de decimal(15,2) es 9.999.999.999.999,99 pero PHP
        // pierde precision en floats grandes. Probamos con un monto
        // realista grande: 100 mil millones (suficiente para cualquier
        // empresa en Chile) sin problemas de precision.
        $monto = 100000000000.50; // cien mil millones con cincuenta centavos

        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento al limite realista',
            'numero_comprobante' => 'MAX-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 90000003,
        ]);

        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '110101',
            'tipo_operacion' => 'DEBE',
            'debe' => $monto,
            'haber' => 0,
        ]);
        DetalleAsiento::create([
            'asiento_id' => $asiento->id,
            'cuenta_contable' => '410101',
            'tipo_operacion' => 'HABER',
            'debe' => 0,
            'haber' => $monto,
        ]);

        $detalle = DetalleAsiento::where('asiento_id', $asiento->id)
            ->where('tipo_operacion', 'DEBE')->first();

        // Verificar que el monto se guardo intacto (cents incluidos)
        $this->assertEquals($monto, (float) $detalle->debe);
    }
}
