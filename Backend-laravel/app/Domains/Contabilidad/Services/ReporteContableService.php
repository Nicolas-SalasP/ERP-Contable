<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use Exception;

class ReporteContableService
{
    private function aplicarFiltroEstado($query, int $filtro, string $columnaEstado = 'estado')
    {
        if ($filtro === 1) {
            $query->whereNotIn($columnaEstado, ['ANULADO', 'RECLASIFICADO']);
        } elseif ($filtro === 2) {
            $query->whereIn($columnaEstado, ['ANULADO', 'RECLASIFICADO']);
        }

        return $query;
    }

    public function generarLibroMayor(int $empresaId, string $cuentaCodigo, string $fechaInicio, string $fechaFin, int $filtro = 1)
    {
        $cuenta = PlanCuenta::where('empresa_id', $empresaId)
            ->where('codigo', $cuentaCodigo)
            ->first();

        if (!$cuenta) {
            throw new Exception("La cuenta contable {$cuentaCodigo} no existe.");
        }

        $esDeudora = in_array($cuenta->tipo, ['ACTIVO', 'GASTO']);

        $queryAnteriores = DetalleAsiento::join('asientos_contables', 'detalles_asiento.asiento_id', '=', 'asientos_contables.id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.fecha', '<', $fechaInicio)
            ->where('detalles_asiento.cuenta_contable', $cuentaCodigo);

        $queryAnteriores = $this->aplicarFiltroEstado($queryAnteriores, $filtro, 'asientos_contables.estado');

        $totalesAnteriores = $queryAnteriores->selectRaw('COALESCE(SUM(debe), 0) as total_debe, COALESCE(SUM(haber), 0) as total_haber')->first();

        $saldoInicial = $esDeudora
            ? ($totalesAnteriores->total_debe - $totalesAnteriores->total_haber)
            : ($totalesAnteriores->total_haber - $totalesAnteriores->total_debe);

        $queryMovimientos = DetalleAsiento::with('asiento')
            ->join('asientos_contables', 'detalles_asiento.asiento_id', '=', 'asientos_contables.id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin])
            ->where('detalles_asiento.cuenta_contable', $cuentaCodigo)
            ->orderBy('asientos_contables.fecha', 'asc')
            ->orderBy('asientos_contables.id', 'asc')
            ->select('detalles_asiento.*');

        $queryMovimientos = $this->aplicarFiltroEstado($queryMovimientos, $filtro, 'asientos_contables.estado');
        $movimientos = $queryMovimientos->get();

        $saldoAcumulado = $saldoInicial;
        $lineas = [];

        foreach ($movimientos as $mov) {
            $saldoAcumulado += $esDeudora
                ? ($mov->debe - $mov->haber)
                : ($mov->haber - $mov->debe);

            $lineas[] = [
                'fecha' => $mov->asiento->fecha->format('Y-m-d'),
                'comprobante' => $mov->asiento->numero_comprobante ?? $mov->asiento->id,
                'glosa' => $mov->descripcion_extensa ?: $mov->asiento->glosa,
                'estado' => $mov->asiento->estado,
                'debe' => $mov->debe,
                'haber' => $mov->haber,
                'saldo' => round($saldoAcumulado, 2)
            ];
        }

        return [
            'cuenta' => "{$cuenta->codigo} - {$cuenta->nombre}",
            'naturaleza' => $esDeudora ? 'Deudora' : 'Acreedora',
            'saldo_inicial' => round($saldoInicial, 2),
            'movimientos' => $lineas,
            'saldo_final' => round($saldoAcumulado, 2)
        ];
    }

    public function generarBalanceComprobacion(int $empresaId, string $fechaInicio, string $fechaFin, int $filtro = 1): array
    {
        $query = DetalleAsiento::join('asientos_contables', 'detalles_asiento.asiento_id', '=', 'asientos_contables.id')
            ->join('plan_cuentas', function ($join) {
                $join->on('detalles_asiento.cuenta_contable', '=', 'plan_cuentas.codigo')
                    ->on('plan_cuentas.empresa_id', '=', 'asientos_contables.empresa_id');
            })
            ->where('asientos_contables.empresa_id', $empresaId)
            ->whereBetween('asientos_contables.fecha', [$fechaInicio, $fechaFin]);

        $query = $this->aplicarFiltroEstado($query, $filtro, 'asientos_contables.estado');

        $filas = $query
            ->groupBy('detalles_asiento.cuenta_contable', 'plan_cuentas.nombre', 'plan_cuentas.tipo')
            ->orderBy('plan_cuentas.codigo', 'asc')
            ->selectRaw(
                'detalles_asiento.cuenta_contable as codigo,
                 plan_cuentas.nombre,
                 plan_cuentas.tipo,
                 COALESCE(SUM(detalles_asiento.debe), 0) as total_debe,
                 COALESCE(SUM(detalles_asiento.haber), 0) as total_haber'
            )
            ->get();

        $cuentas = [];
        $totalDebe        = 0.0;
        $totalHaber       = 0.0;
        $totalSaldoDeudor = 0.0;
        $totalSaldoAcreedor = 0.0;

        foreach ($filas as $fila) {
            $debe   = (float) $fila->total_debe;
            $haber  = (float) $fila->total_haber;

            if ($debe >= $haber) {
                $saldoDeudor   = round($debe - $haber, 2);
                $saldoAcreedor = 0.0;
            } else {
                $saldoDeudor   = 0.0;
                $saldoAcreedor = round($haber - $debe, 2);
            }

            $totalDebe          += $debe;
            $totalHaber         += $haber;
            $totalSaldoDeudor   += $saldoDeudor;
            $totalSaldoAcreedor += $saldoAcreedor;

            $cuentas[] = [
                'codigo'         => $fila->codigo,
                'nombre'         => $fila->nombre,
                'tipo'           => $fila->tipo,
                'debe'           => round($debe, 2),
                'haber'          => round($haber, 2),
                'saldo_deudor'   => $saldoDeudor,
                'saldo_acreedor' => $saldoAcreedor,
            ];
        }

        return [
            'periodo' => [
                'inicio' => $fechaInicio,
                'fin'    => $fechaFin,
            ],
            'cuentas' => $cuentas,
            'totales' => [
                'debe'           => round($totalDebe, 2),
                'haber'          => round($totalHaber, 2),
                'saldo_deudor'   => round($totalSaldoDeudor, 2),
                'saldo_acreedor' => round($totalSaldoAcreedor, 2),
            ],
        ];
    }

    public function generarLibroDiario(int $empresaId, string $fechaInicio, string $fechaFin, int $filtro = 1, ?string $search = null)
    {
        $query = AsientoContable::with(['detalles.cuenta'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc');

        $query = $this->aplicarFiltroEstado($query, $filtro, 'estado');
        if (!empty($search)) {
            $query->where('glosa', 'LIKE', "%{$search}%");
        }

        return $query->get();
    }
}