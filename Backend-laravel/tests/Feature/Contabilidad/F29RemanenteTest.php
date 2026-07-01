<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\ImpuestosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Verifica la persistencia y aplicación del remanente de IVA entre periodos F29.
 *
 * El remanente se genera cuando el IVA crédito supera al IVA débito en un mes
 * (art. 26 Ley IVA). El mes siguiente debe leerlo del asiento MAYORIZADO previo
 * y sumarlo al crédito fiscal, y el asiento debe consumirlo con una línea HABER
 * en cuenta 152542 (Remanente IVA F29).
 */
class F29RemanenteTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private ImpuestosService $service;
    private $empresa;
    private $usuario;
    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin(
            [],
            ['rol_id' => $this->rolSuperAdmin->id]
        );

        $this->service = app(ImpuestosService::class);

        $this->proveedor = Proveedor::create([
            'empresa_id'     => $this->empresa->id,
            'rut'            => '76555444-3',
            'razon_social'   => 'Proveedor Remanente Test',
            'codigo_interno' => 'PRT',
            'pais_iso'       => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        // Cuentas contables necesarias para el ciclo completo del F29
        $this->crearCuenta('152540', 'IVA Crédito Fiscal', 'ACTIVO');
        $this->crearCuenta('353360', 'IVA Débito Fiscal', 'PASIVO');
        $this->crearCuenta('152542', 'Remanente IVA F29', 'ACTIVO');
        $this->crearCuenta('152541', 'PPM por Recuperar', 'ACTIVO');
        $this->crearCuenta('353365', 'Impuesto a Pagar F29', 'PASIVO');
        $this->crearCuenta('353350', 'IVA por Pagar', 'PASIVO');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function crearCuenta(string $codigo, string $nombre, string $tipo): void
    {
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo'     => $codigo,
            'nombre'     => $nombre,
            'tipo'       => $tipo,
            'imputable'  => true,
            'activo'     => true,
        ]);
    }

    /**
     * Crea una factura de compra para el periodo indicado.
     */
    private function crearCompra(int $mes, int $anio, float $montoNeto, float $montoIva, int $consecutivo = 1): void
    {
        $dia = str_pad((string) $consecutivo, 2, '0', STR_PAD_LEFT);
        Factura::create([
            'empresa_id'            => $this->empresa->id,
            'proveedor_id'          => $this->proveedor->id,
            'numero_factura'        => "FC-REM-{$mes}{$anio}-{$consecutivo}",
            'tipo'                  => 'COMPRA',
            'codigo_unico'          => Factura::generarCodigoUnico(),
            'fecha_emision'         => "$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-{$dia}",
            'monto_neto'            => $montoNeto,
            'monto_iva'             => $montoIva,
            'monto_bruto'           => $montoNeto + $montoIva,
            'estado'                => 'REGISTRADA',
            'es_documento_exterior' => false,
        ]);
    }

    /**
     * Inserta un DTE de venta en sii_dte_emitido (tipo_dte 33 = Factura Afecta).
     */
    private function insertarDteVenta(int $mes, int $anio, float $montoNeto, float $iva, int $folio): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'            => $this->empresa->id,
            'tipo_dte'              => 33,
            'folio'                 => $folio,
            'fecha_emision'         => "$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-15",
            'estado'                => 'ACEPTADO',
            'monto_neto'            => $montoNeto,
            'monto_exento'          => 0,
            'iva'                   => $iva,
            'monto_total'           => $montoNeto + $iva,
            'emisor_rut'            => '76000001-K',
            'emisor_razon_social'   => 'Empresa Test SA',
            'receptor_rut'          => '11111111-1',
            'receptor_razon_social' => 'Cliente Test',
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Cuando el mes anterior tiene un F29 mayorizado con remanente (DEBE en 152542),
     * simularF29 del mes siguiente debe incluir ese remanente en iva_credito y
     * exponerlo en remanente_mes_anterior.
     */
    public function test_remanente_mes_anterior_se_aplica_como_credito_fiscal(): void
    {
        // Mes 5/2026: compras grandes, sin ventas → remanente generado al ejecutar
        $this->crearCompra(5, 2026, 2_631_578.0, 500_000.0);
        $this->service->ejecutarF29($this->empresa->id, $this->usuario->id, 5, 2026);

        // Mes 6/2026: sin compras ni ventas — solo se simula
        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(
            500_000.0,
            $resultado['compras']['remanente_mes_anterior'],
            'El remanente del mes anterior debe ser 500.000'
        );
        $this->assertEquals(
            500_000.0,
            $resultado['compras']['iva_credito'],
            'El iva_credito total debe incluir el remanente del mes anterior'
        );
        $this->assertEquals(
            0.0,
            $resultado['compras']['iva_credito_facturas'],
            'iva_credito_facturas debe ser 0 (sin compras en mes 6)'
        );
        // El IVA determinado debe reflejar el crédito acumulado
        $this->assertEquals(
            -500_000.0,
            $resultado['resumen']['iva_determinado'],
            'Con solo remanente y sin débito, el IVA determinado es negativo'
        );
    }

    /**
     * Si el mes anterior no fue cerrado (no hay asiento MAYORIZADO de F29),
     * el remanente_mes_anterior debe ser 0 y iva_credito debe ser solo el
     * de las facturas del periodo.
     */
    public function test_remanente_no_aparece_si_mes_anterior_no_fue_cerrado(): void
    {
        // Mes 5/2026: hay compras pero NO se ejecuta el F29
        $this->crearCompra(5, 2026, 2_631_578.0, 500_000.0);

        // Mes 6/2026: se simula sin haber ejecutado el mes anterior
        $this->crearCompra(6, 2026, 52_631.0, 10_000.0);
        $resultado = $this->service->simularF29($this->empresa->id, 6, 2026);

        $this->assertEquals(
            0.0,
            $resultado['compras']['remanente_mes_anterior'],
            'Sin F29 ejecutado en mes 5, el remanente debe ser 0'
        );
        $this->assertEquals(
            10_000.0,
            $resultado['compras']['iva_credito'],
            'iva_credito debe ser solo el de las facturas del mes 6'
        );
        $this->assertEquals(
            10_000.0,
            $resultado['compras']['iva_credito_facturas'],
            'iva_credito_facturas debe ser 10.000'
        );
    }

    /**
     * Al ejecutar el F29 del mes siguiente a uno con remanente, el asiento
     * resultante debe contener una línea HABER en cuenta 152542 que cancela
     * el activo de remanente del mes previo.
     */
    public function test_ejecutar_f29_consume_remanente_con_linea_haber_152542(): void
    {
        // Mes 5/2026: compras → remanente 500.000
        $this->crearCompra(5, 2026, 2_631_578.0, 500_000.0);
        $this->service->ejecutarF29($this->empresa->id, $this->usuario->id, 5, 2026);

        // Mes 6/2026: sin compras, sin ventas; el remanente es el único crédito
        $this->service->ejecutarF29($this->empresa->id, $this->usuario->id, 6, 2026);

        $asientoMes6 = AsientoContable::where('empresa_id', $this->empresa->id)
            ->where('glosa', 'Centralización F29 - 06/2026')
            ->where('estado', 'MAYORIZADO')
            ->first();

        $this->assertNotNull($asientoMes6, 'Debe existir el asiento F29 del mes 6');

        $tieneHaberRemanente = DB::table('detalles_asiento')
            ->where('asiento_id', $asientoMes6->id)
            ->where('cuenta_contable', '152542')
            ->where('haber', '>', 0)
            ->exists();

        $this->assertTrue(
            $tieneHaberRemanente,
            'El asiento F29 del mes 6 debe incluir una línea HABER en cuenta 152542 para consumir el remanente anterior'
        );

        // Verificar el monto exacto de la línea HABER
        $haberRemanente = (float) DB::table('detalles_asiento')
            ->where('asiento_id', $asientoMes6->id)
            ->where('cuenta_contable', '152542')
            ->where('haber', '>', 0)
            ->sum('haber');

        $this->assertEquals(
            500_000.0,
            $haberRemanente,
            'La línea HABER en 152542 debe ser igual al remanente generado en mes 5'
        );

        // Verificar que el asiento cuadra (partida doble)
        $totalDebe  = (float) DB::table('detalles_asiento')->where('asiento_id', $asientoMes6->id)->sum('debe');
        $totalHaber = (float) DB::table('detalles_asiento')->where('asiento_id', $asientoMes6->id)->sum('haber');
        $this->assertEquals(
            round($totalDebe, 2),
            round($totalHaber, 2),
            'El asiento F29 mes 6 debe cuadrar (DEBE = HABER)'
        );
    }

    /**
     * Cuando no existe F29 ejecutado en el mes anterior, el comportamiento
     * del servicio es idéntico al original: remanente_mes_anterior = 0 y
     * iva_credito = solo facturas del periodo.
     */
    public function test_f29_sin_remanente_anterior_no_cambia_comportamiento(): void
    {
        // Mes 7/2026: compras normales, venta con IVA mayor → saldo a pagar, sin remanente previo
        $this->crearCompra(7, 2026, 263_157.0, 50_000.0);
        $this->insertarDteVenta(7, 2026, 1_000_000.0, 190_000.0, 100);

        $resultado = $this->service->simularF29($this->empresa->id, 7, 2026);

        $this->assertEquals(
            0.0,
            $resultado['compras']['remanente_mes_anterior'],
            'Sin F29 previo, remanente_mes_anterior debe ser 0'
        );
        $this->assertEquals(
            50_000.0,
            $resultado['compras']['iva_credito_facturas'],
            'iva_credito_facturas debe ser solo el de las compras del periodo'
        );
        $this->assertEquals(
            50_000.0,
            $resultado['compras']['iva_credito'],
            'iva_credito total debe coincidir con iva_credito_facturas cuando no hay remanente'
        );
        $this->assertEquals(
            190_000.0,
            $resultado['ventas']['iva_debito'],
            'iva_debito debe reflejar el IVA de las ventas del periodo'
        );
        // IVA a pagar = débito - crédito = 190.000 - 50.000 = 140.000
        $this->assertEquals(
            140_000.0,
            $resultado['resumen']['iva_determinado'],
            'IVA determinado debe ser 140.000 (sin remanente previo)'
        );
        $this->assertEquals(
            0.0,
            $resultado['resumen']['remanente'],
            'No debe haber remanente nuevo cuando el débito supera al crédito'
        );
    }
}
