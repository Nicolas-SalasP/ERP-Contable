<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Contabilidad\Services\F29DriftService;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Contabilidad\Exceptions\ContabilidadException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ImpuestosService
{
    /** Ventas afectas reales del periodo desde DTEs emitidos (no cotizaciones, que inflaban el F29): suma facturas/boletas/ND y resta NC, excluyendo borradores/rechazados/anulados. */
    private function resumenVentasAfectas(int $empresaId, string $fechaInicio, string $fechaFin): array
    {
        $estadosEmitidos = [
            SiiDteEmitido::ESTADO_FIRMADO,
            SiiDteEmitido::ESTADO_ENVIADO_SII,
            SiiDteEmitido::ESTADO_EN_PROCESO_SII,
            SiiDteEmitido::ESTADO_ACEPTADO,
            SiiDteEmitido::ESTADO_ACEPTADO_CON_REPAROS,
        ];

        $tiposVenta = [
            SiiDteEmitido::TIPO_FACTURA,
            SiiDteEmitido::TIPO_FACTURA_EXENTA,
            SiiDteEmitido::TIPO_BOLETA,
            SiiDteEmitido::TIPO_BOLETA_EXENTA,
            SiiDteEmitido::TIPO_NOTA_DEBITO,
            SiiDteEmitido::TIPO_FACTURA_EXPORTACION,
            SiiDteEmitido::TIPO_NOTA_DEBITO_EXPORTACION,
        ];

        $tiposNotaCredito = [
            SiiDteEmitido::TIPO_NOTA_CREDITO,
            SiiDteEmitido::TIPO_NOTA_CREDITO_EXPORTACION,
        ];

        $base = fn () => DB::table('sii_dte_emitido')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->whereIn('estado', $estadosEmitidos);

        $ventas = $base()->whereIn('tipo_dte', $tiposVenta);
        $notasCredito = $base()->whereIn('tipo_dte', $tiposNotaCredito);

        $neto = (float) $ventas->sum('monto_neto') - (float) $notasCredito->sum('monto_neto');
        $iva = (float) $ventas->sum('iva') - (float) $notasCredito->sum('iva');
        $cantidad = $ventas->count();

        return [
            'neto' => $neto,
            'iva' => $iva,
            'cantidad' => $cantidad,
        ];
    }

    public function simularF29(int $empresaId, int $mes, int $anio)
    {
        $fechaInicio = "$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        // Ventas reales del periodo: DTEs emitidos (no cotizaciones). Ver resumenVentasAfectas().
        $ventasResumen = $this->resumenVentasAfectas($empresaId, $fechaInicio, $fechaFin);
        $totalVentasNeto = $ventasResumen['neto'];
        $ivaDebito = $ventasResumen['iva'];

        $compras = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'ANULADA')
            ->where('es_documento_exterior', false)
            ->get();
        $totalComprasNeto = $compras->sum('monto_neto');
        $ivaCreditoFacturas = (float) $compras->sum('monto_iva');

        // Remanente IVA del mes anterior (DEBE en cuenta 152542 si el F29 previo fue mayorizado) se suma al crédito fiscal actual (art. 26 Ley IVA).
        $mesPrev  = $mes === 1 ? 12 : $mes - 1;
        $anioPrev = $mes === 1 ? $anio - 1 : $anio;
        $glosaPrev = "Centralización F29 - " . str_pad((string) $mesPrev, 2, '0', STR_PAD_LEFT) . "/$anioPrev";

        $remanenteMesAnterior = (float) DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'impuestos')
            ->where('asientos_contables.glosa', $glosaPrev)
            ->where('asientos_contables.estado', 'MAYORIZADO')
            ->where('detalles_asiento.cuenta_contable', '152542')
            ->sum('detalles_asiento.debe');

        // Las líneas HABER en 152542 tienen debe=0, por lo que sum('debe') devuelve solo el remanente nuevo generado.
        $ivaCredito = $ivaCreditoFacturas + $remanenteMesAnterior;

        $retencionHonorarios = (int) \Illuminate\Support\Facades\DB::table('honorarios_recibidos')
            ->where('empresa_id', $empresaId)
            ->whereNull('deleted_at')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->sum('monto_retencion');
        $empresaRow = DB::table('empresas')->where('id', $empresaId)->first();

        // PPM diferenciado por régimen tributario
        if (($empresaRow->regimen_tributario ?? '') === '14_D8') {
            $tasaTabla = DB::table('tasas_ppm_propyme')->where('anio', $anio)->first();
            if ($tasaTabla) {
                // Umbral de 50.000 UF aproximado en pesos (50.000 × ~$38.000 = $1.900.000.000).
                $ingresosAnioAnterior = (float) DB::table('sii_dte_emitido')
                    ->where('empresa_id', $empresaId)
                    ->whereYear('fecha_emision', $anio - 1)
                    ->whereIn('estado', ['FIRMADO', 'ENVIADO_SII', 'EN_PROCESO_SII', 'ACEPTADO', 'ACEPTADO_CON_REPAROS'])
                    ->whereIn('tipo_dte', [33, 34, 39, 41, 56, 110, 111])
                    ->sum('monto_neto');
                $umbral50000UF = 1_900_000_000;
                $tasaPpm = $ingresosAnioAnterior > $umbral50000UF
                    ? (float) $tasaTabla->tasa_sobre_50000uf_pct
                    : (float) $tasaTabla->tasa_base_pct;
            } else {
                $tasaPpm = 0.125; // fallback rebaja transitoria si no hay tabla configurada
            }
        } else {
            $tasaPpm = (float) ($empresaRow->ppm_pct ?? 1.00);
        }

        $montoPpm = round($totalVentasNeto * ($tasaPpm / 100));

        $ivaDeterminado = $ivaDebito - $ivaCredito;
        $totalAPagar = 0;
        $remanenteSiguienteMes = 0;

        if ($ivaDeterminado > 0) {
            $totalAPagar = $ivaDeterminado + $retencionHonorarios + $montoPpm;
        } else {
            $remanenteSiguienteMes = abs($ivaDeterminado);
            $totalAPagar = $retencionHonorarios + $montoPpm;
        }

        $glosaCierre = "Centralización F29 - " . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "/$anio";
        $yaCerrado = DB::table('asientos_contables')->where('empresa_id', $empresaId)->where('origen_modulo', 'impuestos')->where('glosa', $glosaCierre)->where('estado', 'MAYORIZADO')->exists();

        // Alerta de drift: si una factura/DTE de este período se anuló después de centralizar el F29, no se relanza solo (ver F29DriftService).
        $estadoDrift = app(F29DriftService::class)->estado($empresaId, $mes, $anio);

        $lineasF29 = $this->lineasFormularioF29([
            'ventas'      => ['neto' => $totalVentasNeto, 'iva_debito' => $ivaDebito, 'cantidad' => $ventasResumen['cantidad']],
            'compras'     => ['neto' => $totalComprasNeto, 'iva_credito_facturas' => $ivaCreditoFacturas, 'remanente_mes_anterior' => $remanenteMesAnterior],
            'retenciones' => $retencionHonorarios,
            'ppm'         => ['tasa' => $tasaPpm, 'monto' => $montoPpm],
            'resumen'     => ['iva_determinado' => $ivaDeterminado, 'remanente' => $remanenteSiguienteMes, 'total_a_pagar' => $totalAPagar],
        ]);

        return [
            'periodo'     => str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "/$anio",
            'ya_cerrado'  => $yaCerrado,
            'desactualizado' => $estadoDrift['desactualizado'],
            'motivo_desactualizacion' => $estadoDrift['motivo'],
            'ventas'      => ['cantidad' => $ventasResumen['cantidad'], 'neto' => $totalVentasNeto, 'iva_debito' => $ivaDebito],
            'compras'     => [
                'cantidad'               => $compras->count(),
                'neto'                   => $totalComprasNeto,
                'iva_credito'            => $ivaCredito,
                'iva_credito_facturas'   => $ivaCreditoFacturas,
                'remanente_mes_anterior' => $remanenteMesAnterior,
            ],
            'retenciones' => $retencionHonorarios,
            'ppm'         => ['tasa' => $tasaPpm, 'monto' => $montoPpm],
            'resumen'     => ['iva_determinado' => $ivaDeterminado, 'remanente' => $remanenteSiguienteMes, 'total_a_pagar' => $totalAPagar],
            'lineas_f29'  => $lineasF29,
        ];
    }

    /**
     * Mapea los datos de simularF29 a las líneas del Formulario 29 SII (invariantes: L27=L24+L26; L28/L36 mutuamente excluyentes; L89=L91=total_a_pagar).
     *
     * @param array<string, mixed> $sim
     * @return array<string, array{desc: string, valor: int|float}>
     */
    private function lineasFormularioF29(array $sim): array
    {
        $ivaDebito    = (float) $sim['ventas']['iva_debito'];
        $ivaCredFac   = (float) $sim['compras']['iva_credito_facturas'];
        $remanente    = (float) $sim['compras']['remanente_mes_anterior'];
        $totalDebito  = $ivaDebito;
        $totalCredito = $ivaCredFac + $remanente;
        $diferencia   = $totalDebito - $totalCredito;

        return [
            // SECCIÓN A — VENTAS Y DÉBITOS
            'L1'  => ['desc' => 'Ventas y/o Serv. del Giro Netos (afectos)',    'valor' => (int) round((float) $sim['ventas']['neto'])],
            'L2'  => ['desc' => 'Ventas y/o Serv. Exentos / No Afectos',        'valor' => 0],
            'L7'  => ['desc' => 'Débito Fiscal',                                 'valor' => (int) round($ivaDebito)],
            'L11' => ['desc' => 'Total Débito Fiscal',                           'valor' => (int) round($totalDebito)],

            // SECCIÓN B — COMPRAS Y CRÉDITOS
            'L20' => ['desc' => 'Compras Internas Afectas del Giro (neto)',      'valor' => (int) round((float) $sim['compras']['neto'])],
            'L24' => ['desc' => 'Total Crédito Fiscal del Período',              'valor' => (int) round($ivaCredFac)],
            'L26' => ['desc' => 'Remanente Crédito Fiscal Período Anterior',     'valor' => (int) round($remanente)],
            'L27' => ['desc' => 'Total Crédito Fiscal Disponible',               'valor' => (int) round($totalCredito)],
            'L28' => ['desc' => 'IVA Determinado (L11 - L27)',                   'valor' => $diferencia > 0 ? (int) round($diferencia) : 0],
            'L36' => ['desc' => 'Remanente para el Período Siguiente',           'valor' => $diferencia < 0 ? (int) round(abs($diferencia)) : 0],

            // SECCIÓN C — PPM
            'L63' => ['desc' => 'Base Imponible PPM',                            'valor' => (int) round((float) $sim['ventas']['neto'])],
            'L64' => ['desc' => 'Tasa PPM (%)',                                  'valor' => (float) $sim['ppm']['tasa']],
            'L65' => ['desc' => 'Monto PPM',                                     'valor' => (int) round((float) $sim['ppm']['monto'])],

            // SECCIÓN D — RETENCIONES
            'L49' => ['desc' => 'Retención Honorarios (Art. 74 N°2)',            'valor' => (int) $sim['retenciones']],

            // SECCIÓN E — DETERMINACIÓN IMPUESTO
            'L89' => ['desc' => 'Total Impuesto Determinado',                    'valor' => (int) round((float) $sim['resumen']['total_a_pagar'])],
            'L91' => ['desc' => 'Total a Pagar (o Remanente)',                   'valor' => (int) round((float) $sim['resumen']['total_a_pagar'])],
        ];
    }

    public function ejecutarF29(int $empresaId, int $usuarioId, int $mes, int $anio)
    {
        $cuentasBase = DB::table('plan_cuentas')->where('empresa_id', $empresaId)
            ->whereIn('codigo', ['152540', '353360'])->pluck('codigo')->toArray();

        if (count($cuentasBase) < 2) {
            throw ContabilidadException::regla("Falta configuración: Se requieren cuentas de IVA Crédito (152540) y Débito (353360) en el plan de la empresa.");
        }

        // Lock atómico: evita race check-then-act entre requests concurrentes.
        $lockKey = "centralizacion-f29-{$empresaId}-{$anio}-{$mes}";
        if (!Cache::add($lockKey, true, 30)) {
            throw ContabilidadException::conflicto("La centralización del F29 para {$mes}/{$anio} ya está en curso. Espere unos segundos y vuelva a intentarlo.");
        }

        try {
            $simulacion = $this->simularF29($empresaId, $mes, $anio);

            if ($simulacion['ya_cerrado']) {
                throw ContabilidadException::conflicto("El F29 para este período ya ha sido centralizado.");
            }

            if ($simulacion['ventas']['iva_debito'] == 0
                && $simulacion['compras']['iva_credito'] == 0
                && $simulacion['ppm']['monto'] == 0
                && $simulacion['retenciones'] == 0) {
                throw ContabilidadException::regla("No hay movimientos de IVA, PPM ni retenciones para centralizar en este período.");
            }

            $detalles = [];

            if ($simulacion['ventas']['iva_debito'] > 0) {
                $detalles[] = ['cuenta_contable' => '353360', 'debe' => $simulacion['ventas']['iva_debito'], 'haber' => 0, 'glosa_detalle' => 'Reversa IVA Débito'];
            }
            if ($simulacion['ppm']['monto'] > 0) {
                $detalles[] = ['cuenta_contable' => '152541', 'debe' => $simulacion['ppm']['monto'], 'haber' => 0, 'glosa_detalle' => 'PPM por Recuperar'];
            }
            if ($simulacion['resumen']['remanente'] > 0) {
                $detalles[] = ['cuenta_contable' => '152542', 'debe' => $simulacion['resumen']['remanente'], 'haber' => 0, 'glosa_detalle' => 'Remanente IVA F29'];
            }
            // Reversa IVA Crédito: solo el proveniente de facturas de compra del periodo.
            if ($simulacion['compras']['iva_credito_facturas'] > 0) {
                $detalles[] = ['cuenta_contable' => '152540', 'debe' => 0, 'haber' => $simulacion['compras']['iva_credito_facturas'], 'glosa_detalle' => 'Reversa IVA Crédito'];
            }
            // HABER en 152542 cancela el activo generado en el asiento F29 del mes previo (mantiene partida doble).
            if ($simulacion['compras']['remanente_mes_anterior'] > 0) {
                $detalles[] = ['cuenta_contable' => '152542', 'debe' => 0, 'haber' => $simulacion['compras']['remanente_mes_anterior'], 'glosa_detalle' => 'Aplicación remanente IVA mes anterior'];
            }
            if ($simulacion['resumen']['total_a_pagar'] > 0) {
                $detalles[] = ['cuenta_contable' => '353365', 'debe' => 0, 'haber' => $simulacion['resumen']['total_a_pagar'], 'glosa_detalle' => 'Impuesto a Pagar F29'];
            }

            $fechaAsiento = date('Y-m-t', strtotime("$anio-" . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "-01"));

            $asientoService = app(AsientoContableService::class);

            $cabecera = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'fecha' => $fechaAsiento,
                'glosa' => "Centralización F29 - " . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . "/$anio",
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'impuestos',
                'estado' => 'MAYORIZADO'
            ];

            return DB::transaction(function () use ($asientoService, $cabecera, $detalles) {
                return $asientoService->registrarAsiento($cabecera, $detalles);
            });
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function preCalculoRenta(int $empresaId, int $anio_comercial)
    {
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        $regimen = $empresa->regimen_tributario ?? '14_A';

        $fechaInicio = "$anio_comercial-01-01";
        $fechaFin = "$anio_comercial-12-31";

        $esFlujoCaja = in_array($regimen, ['14_D3', '14_D8']);
        $tasaImpuesto = ($regimen === '14_A') ? 27.0 : (($regimen === '14_D3') ? 10.0 : 0.0);

        // Ingresos por ventas reales: DTEs emitidos (no cotizaciones). Ver resumenVentasAfectas().
        $totalIngresos = $this->resumenVentasAfectas($empresaId, $fechaInicio, $fechaFin)['neto'];

        $queryCompras = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'COMPRA')
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'ANULADA');

        $totalCostosGastos = (float) $queryCompras->sum('monto_neto');

        // 'activos_depreciacion' (depreciación mensual) y 'activos' (bajas) usan origen_modulo
        // distinto desde el fix de reversa (ver ActivoFijoService::depreciarMes); se incluyen
        // ambos aquí para no perder cobertura de la que ya existía antes del fix.
        $totalDepreciacion = (float) DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->whereIn('asientos_contables.origen_modulo', ['activos', 'activos_depreciacion'])
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('detalles_asiento.tipo_operacion', 'DEBE')
            ->sum('detalles_asiento.debe');

        $resultadoCM = $this->calcularResultadoCMAnio($empresaId, $anio_comercial);
        $ingresoCM = $resultadoCM['ingreso_neto'];
        $gastoCM = $resultadoCM['gasto_neto'];

        $baseImponible = max(0, (
            $totalIngresos
            - $totalCostosGastos
            - $totalDepreciacion
            + $ingresoCM
            - $gastoCM
        ));

        $impuestoRenta = round($baseImponible * ($tasaImpuesto / 100));

        $ppmAcumulado = (float) DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'impuestos')
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('detalles_asiento.cuenta_contable', '152541')
            ->where('detalles_asiento.tipo_operacion', 'DEBE')
            ->sum('detalles_asiento.debe');

        $saldoFinal = $impuestoRenta - $ppmAcumulado;

        return [
            'anio_comercial' => $anio_comercial,
            'anio_tributario' => $anio_comercial + 1,
            'regimen_tributario' => $regimen,
            'regla_calculo' => $esFlujoCaja ? 'FLUJO_DE_CAJA' : 'DEVENGADO',
            'ingresos' => [
                'ventas_netas' => $totalIngresos,
                'otros_ingresos' => 0,
            ],
            'gastos' => [
                'costos_directos' => $totalCostosGastos,
                'depreciacion' => $totalDepreciacion,
                'remuneraciones' => 0,
            ],
            'correccion_monetaria' => [
                'aplica' => $resultadoCM['aplica'],
                'ejecutada' => $resultadoCM['ejecutada'],
                'periodos' => $resultadoCM['periodos'],
                'ingreso_cm' => $ingresoCM,
                'gasto_cm' => $gastoCM,
                'resultado_neto' => $ingresoCM - $gastoCM,
            ],
            'resultado' => [
                'base_imponible' => $baseImponible,
                'tasa_impuesto' => $tasaImpuesto,
                'impuesto_renta' => $impuestoRenta,
            ],
            'creditos' => [
                'ppm_acumulado' => $ppmAcumulado,
            ],
            'liquidacion' => [
                'saldo_final' => abs($saldoFinal),
                'tipo_saldo' => $saldoFinal > 0 ? 'A_PAGAR' : 'DEVOLUCION',
            ],
        ];
    }

    private function calcularResultadoCMAnio(int $empresaId, int $anio): array
    {
        $cuentasIngresoCM = ['811001', '811002'];
        $cuentasGastoCM = ['821001', '821002', '821003'];

        $ejecucionesAnio = DB::table('cm_ejecuciones')
            ->where('empresa_id', $empresaId)
            ->where('periodo_anio', $anio)
            ->where('estado', 'ejecutada')
            ->count();

        if ($ejecucionesAnio === 0) {
            return [
                'aplica' => DB::table('cm_configuracion_empresa')
                    ->where('empresa_id', $empresaId)
                    ->value('aplica_cm') ?? true,
                'ejecutada' => false,
                'periodos' => 0,
                'ingreso_neto' => 0.0,
                'gasto_neto' => 0.0,
            ];
        }

        $fechaInicio = "$anio-01-01";
        $fechaFin = "$anio-12-31";

        $ingresoCM = (float) DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'correccion_monetaria')
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('asientos_contables.estado', 'MAYORIZADO')
            ->whereIn('detalles_asiento.cuenta_contable', $cuentasIngresoCM)
            ->sum('detalles_asiento.haber');

        $gastoCM = (float) DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'correccion_monetaria')
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('asientos_contables.estado', 'MAYORIZADO')
            ->whereIn('detalles_asiento.cuenta_contable', $cuentasGastoCM)
            ->sum('detalles_asiento.debe');

        return [
            'aplica' => true,
            'ejecutada' => true,
            'periodos' => $ejecucionesAnio,
            'ingreso_neto' => $ingresoCM,
            'gasto_neto' => $gastoCM,
        ];
    }

    public function obtenerMapeo(int $empresaId)
    {
        $conceptos = [
            'INGRESOS_GIRO' => 'Ingresos del Giro (Ventas)',
            'OTROS_INGRESOS' => 'Otros Ingresos',
            'COMPRAS' => 'Compras y Proveedores',
            'DEPRECIACION' => 'Depreciación de Activos Fijos',
            'REMUNERACIONES' => 'Remuneraciones Pagadas',
            'HONORARIOS' => 'Honorarios Pagados',
            'ARRIENDOS' => 'Arriendos Pagados',
            'GASTOS_GENERALES' => 'Gastos Generales'
        ];

        $mapeadas = DB::table('mapeo_cuentas_sii')
            ->join('plan_cuentas', function ($join) use ($empresaId) {
                $join->on('mapeo_cuentas_sii.codigo_cuenta', '=', 'plan_cuentas.codigo')
                    ->where('plan_cuentas.empresa_id', '=', $empresaId);
            })
            ->where('mapeo_cuentas_sii.empresa_id', $empresaId)
            ->select('mapeo_cuentas_sii.id', 'mapeo_cuentas_sii.codigo_cuenta', 'plan_cuentas.nombre', 'mapeo_cuentas_sii.concepto_sii')
            ->get();

        $codigosMapeados = $mapeadas->pluck('codigo_cuenta')->toArray();

        $disponibles = DB::table('plan_cuentas')
            ->where('empresa_id', $empresaId)
            ->where('imputable', true)
            ->where(function ($query) {
                $query->where('codigo', 'like', '4%')
                    ->orWhere('codigo', 'like', '5%')
                    ->orWhere('codigo', 'like', '6%')
                    ->orWhere('codigo', 'like', '7%')
                    ->orWhere('codigo', 'like', '8%');
            })
            ->whereNotIn('codigo', $codigosMapeados)
            ->select('codigo', 'nombre')
            ->orderBy('codigo', 'asc')
            ->get();

        return [
            'mapeadas' => $mapeadas,
            'disponibles' => $disponibles,
            'conceptos' => $conceptos
        ];
    }

    public function guardarMapeo(int $empresaId, string $codigoCuenta, string $conceptoSii)
    {
        return DB::table('mapeo_cuentas_sii')->updateOrInsert(
            [
                'empresa_id' => $empresaId,
                'codigo_cuenta' => $codigoCuenta
            ],
            [
                'concepto_sii' => $conceptoSii,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    public function eliminarMapeo(int $empresaId, int $id)
    {
        $eliminado = DB::table('mapeo_cuentas_sii')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->delete();

        if (!$eliminado) {
            // Para el usuario autenticado, un mapeo de otra empresa simplemente no existe.
            throw ContabilidadException::noEncontrado("El mapeo no existe o no pertenece a la empresa.");
        }

        return true;
    }
}