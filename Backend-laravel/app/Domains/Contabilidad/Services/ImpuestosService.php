<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class ImpuestosService
{
    public function simularF29(int $empresaId, int $mes, int $anio)
    {
        $fechaInicio = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
        $fechaFin = date('Y-m-t', strtotime($fechaInicio));

        $ventas = DB::table('cotizaciones')->where('empresa_id', $empresaId)->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])->get();
        $totalVentasNeto = $ventas->sum('monto_neto');
        $ivaDebito = $ventas->sum('monto_iva');

        $compras = DB::table('facturas')->where('empresa_id', $empresaId)->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])->where('estado', '!=', 'ANULADA')->get();
        $totalComprasNeto = $compras->sum('monto_neto');
        $ivaCredito = $compras->sum('monto_iva');

        $retenciones = 0;
        $tasaPpm = 1.00;
        $montoPpm = round($totalVentasNeto * ($tasaPpm / 100));

        $ivaDeterminado = $ivaDebito - $ivaCredito;
        $totalAPagar = 0;
        $remanenteSiguienteMes = 0;

        if ($ivaDeterminado > 0) {
            $totalAPagar = $ivaDeterminado + $retenciones + $montoPpm;
        } else {
            $remanenteSiguienteMes = abs($ivaDeterminado);
            $totalAPagar = $retenciones + $montoPpm;
        }

        $glosaCierre = "Centralización F29 - " . str_pad($mes, 2, '0', STR_PAD_LEFT) . "/$anio";
        $yaCerrado = DB::table('asientos_contables')->where('empresa_id', $empresaId)->where('origen_modulo', 'impuestos')->where('glosa', $glosaCierre)->where('estado', 'MAYORIZADO')->exists();

        return [
            'periodo' => str_pad($mes, 2, '0', STR_PAD_LEFT) . "/$anio",
            'ya_cerrado' => $yaCerrado,
            'ventas' => ['cantidad' => $ventas->count(), 'neto' => $totalVentasNeto, 'iva_debito' => $ivaDebito],
            'compras' => ['cantidad' => $compras->count(), 'neto' => $totalComprasNeto, 'iva_credito' => $ivaCredito],
            'retenciones' => $retenciones,
            'ppm' => ['tasa' => $tasaPpm, 'monto' => $montoPpm],
            'resumen' => ['iva_determinado' => $ivaDeterminado, 'remanente' => $remanenteSiguienteMes, 'total_a_pagar' => $totalAPagar]
        ];
    }

    public function ejecutarF29(int $empresaId, int $usuarioId, int $mes, int $anio)
    {
        $simulacion = $this->simularF29($empresaId, $mes, $anio);

        if ($simulacion['ya_cerrado']) {
            throw new Exception("El F29 para este período ya ha sido centralizado.");
        }

        if ($simulacion['ventas']['iva_debito'] == 0 && $simulacion['compras']['iva_credito'] == 0) {
            throw new Exception("No hay movimientos de IVA para centralizar en este período.");
        }

        $detalles = [];

        if ($simulacion['ventas']['iva_debito'] > 0) {
            $detalles[] = ['cuenta_contable' => '210201', 'debe' => $simulacion['ventas']['iva_debito'], 'haber' => 0, 'glosa_detalle' => 'Reversa IVA Débito'];
        }
        if ($simulacion['ppm']['monto'] > 0) {
            $detalles[] = ['cuenta_contable' => '110403', 'debe' => $simulacion['ppm']['monto'], 'haber' => 0, 'glosa_detalle' => 'PPM por Recuperar'];
        }
        if ($simulacion['resumen']['remanente'] > 0) {
            $detalles[] = ['cuenta_contable' => '110402', 'debe' => $simulacion['resumen']['remanente'], 'haber' => 0, 'glosa_detalle' => 'Remanente IVA F29'];
        }

        if ($simulacion['compras']['iva_credito'] > 0) {
            $detalles[] = ['cuenta_contable' => '110001', 'debe' => 0, 'haber' => $simulacion['compras']['iva_credito'], 'glosa_detalle' => 'Reversa IVA Crédito'];
        }
        if ($simulacion['resumen']['total_a_pagar'] > 0) {
            $detalles[] = ['cuenta_contable' => '210301', 'debe' => 0, 'haber' => $simulacion['resumen']['total_a_pagar'], 'glosa_detalle' => 'IVA por Pagar F29'];
        }

        $fechaAsiento = date('Y-m-t', strtotime("$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01"));

        $asientoService = app(AsientoContableService::class);

        $cabecera = [
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'fecha' => $fechaAsiento,
            'glosa' => "Centralización F29 - " . str_pad($mes, 2, '0', STR_PAD_LEFT) . "/$anio",
            'tipo_asiento' => 'traspaso',
            'origen_modulo' => 'impuestos',
            'estado' => 'MAYORIZADO'
        ];

        return DB::transaction(function () use ($asientoService, $cabecera, $detalles) {
            return $asientoService->registrarAsiento($cabecera, $detalles);
        });
    }

    public function preCalculoRenta(int $empresaId, int $anio_comercial)
    {
        $empresa = DB::table('empresas')->where('id', $empresaId)->first();
        $regimen = $empresa->regimen_tributario ?? '14_A';

        $fechaInicio = "$anio_comercial-01-01";
        $fechaFin = "$anio_comercial-12-31";

        $esFlujoCaja = in_array($regimen, ['14_D3', '14_D8']);
        $tasaImpuesto = ($regimen === '14_A') ? 27.0 : (($regimen === '14_D3') ? 10.0 : 0.0);

        $queryVentas = DB::table('cotizaciones')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin]);
        
        if ($esFlujoCaja) {
        }

        $totalIngresos = $queryVentas->sum('monto_neto');

        $queryCompras = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha_emision', [$fechaInicio, $fechaFin])
            ->where('estado', '!=', 'ANULADA');

        $totalCostosGastos = $queryCompras->sum('monto_neto');

        $totalDepreciacion = DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'activos')
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('detalles_asiento.tipo_operacion', 'DEBE')
            ->sum('detalles_asiento.debe');

        $baseImponible = max(0, ($totalIngresos - $totalCostosGastos - $totalDepreciacion));
        $impuestoRenta = round($baseImponible * ($tasaImpuesto / 100));

        $ppmAcumulado = DB::table('asientos_contables')
            ->join('detalles_asiento', 'asientos_contables.id', '=', 'detalles_asiento.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.origen_modulo', 'impuestos')
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('detalles_asiento.cuenta_contable', '110403')
            ->where('detalles_asiento.tipo_operacion', 'DEBE')
            ->sum('detalles_asiento.debe');

        $saldoFinal = $impuestoRenta - $ppmAcumulado;

        return [
            'anio_comercial' => $anio_comercial,
            'anio_tributario' => $anio_comercial + 1,
            'regimen_tributario' => $regimen,
            'regla_calculo' => $esFlujoCaja ? 'FLUJO_DE_CAJA' : 'DEVENGADO',
            'ingresos' => ['ventas_netas' => $totalIngresos, 'otros_ingresos' => 0],
            'gastos' => ['costos_directos' => $totalCostosGastos, 'depreciacion' => $totalDepreciacion, 'remuneraciones' => 0],
            'resultado' => ['base_imponible' => $baseImponible, 'tasa_impuesto' => $tasaImpuesto, 'impuesto_renta' => $impuestoRenta],
            'creditos' => ['ppm_acumulado' => $ppmAcumulado],
            'liquidacion' => ['saldo_final' => abs($saldoFinal), 'tipo_saldo' => $saldoFinal > 0 ? 'A_PAGAR' : 'DEVOLUCION']
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
            ->join('plan_cuentas', function($join) use ($empresaId) {
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
            ->where(function($query) {
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
        return DB::table('mapeo_cuentas_sii')
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->delete();
    }
    
}