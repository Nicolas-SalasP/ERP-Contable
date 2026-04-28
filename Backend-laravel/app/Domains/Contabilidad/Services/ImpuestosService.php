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
}