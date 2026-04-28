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
                'glosa' => $mov->asiento->glosa,
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

    public function generarLibroDiario(int $empresaId, string $fechaInicio, string $fechaFin, int $filtro = 1)
    {
        $query = AsientoContable::with(['detalles.cuenta'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$fechaInicio, $fechaFin])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc');

        $query = $this->aplicarFiltroEstado($query, $filtro, 'estado');

        return $query->get();
    }
}