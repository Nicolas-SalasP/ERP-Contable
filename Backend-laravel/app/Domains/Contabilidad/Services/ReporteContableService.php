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
        $inicioAnio   = substr($fechaInicio, 0, 4) . '-01-01';
        $fineAnterior = date('Y-m-d', strtotime($fechaInicio . ' -1 day'));

        $mapAnterior = [];
        if ($inicioAnio <= $fineAnterior) {
            $mapAnterior = $this->consultarBalancePeriodo($empresaId, $inicioAnio, $fineAnterior, $filtro);
        }

        $mapMensual = $this->consultarBalancePeriodo($empresaId, $fechaInicio, $fechaFin, $filtro);

        // Incluir TODAS las cuentas del plan, aunque no tengan movimientos en el período
        $catalogo = PlanCuenta::where('empresa_id', $empresaId)
            ->orderBy('codigo')
            ->get(['codigo', 'nombre', 'tipo'])
            ->keyBy('codigo');

        $codigos = $catalogo->keys()
            ->merge(array_keys($mapAnterior))
            ->merge(array_keys($mapMensual))
            ->unique()
            ->sort()
            ->values()
            ->toArray();
        $codigos = array_map('strval', $codigos);

        $cuentas = [];
        $totales = [
            'anterior'  => ['debe' => 0.0, 'haber' => 0.0, 'saldo_deudor' => 0.0, 'saldo_acreedor' => 0.0],
            'mensual'   => ['debe' => 0.0, 'haber' => 0.0, 'saldo_deudor' => 0.0, 'saldo_acreedor' => 0.0],
            'acumulado' => ['debe' => 0.0, 'haber' => 0.0, 'saldo_deudor' => 0.0, 'saldo_acreedor' => 0.0],
        ];

        foreach ($codigos as $codigo) {
            $cuentaCatalogo = $catalogo[$codigo] ?? null;
            $nombreFallback = $cuentaCatalogo !== null ? $cuentaCatalogo->nombre : '';
            $tipoFallback   = $cuentaCatalogo !== null ? $cuentaCatalogo->tipo   : '';

            $ant = $mapAnterior[$codigo] ?? ['nombre' => $nombreFallback, 'tipo' => $tipoFallback, 'debe' => 0.0, 'haber' => 0.0];
            $men = $mapMensual[$codigo]  ?? ['nombre' => $nombreFallback, 'tipo' => $tipoFallback, 'debe' => 0.0, 'haber' => 0.0];

            $antSaldo  = $this->calcularSaldoBalance((float) $ant['debe'], (float) $ant['haber']);
            $menSaldo  = $this->calcularSaldoBalance((float) $men['debe'], (float) $men['haber']);
            $acumDebe  = (float) $ant['debe']  + (float) $men['debe'];
            $acumHaber = (float) $ant['haber'] + (float) $men['haber'];
            $acumSaldo = $this->calcularSaldoBalance($acumDebe, $acumHaber);

            $cuentas[] = [
                'codigo'    => $codigo,
                'nombre'    => $ant['nombre'] ?: $men['nombre'],
                'tipo'      => $ant['tipo']   ?: $men['tipo'],
                'anterior'  => $antSaldo,
                'mensual'   => $menSaldo,
                'acumulado' => $acumSaldo,
            ];

            $totales['anterior']['debe']           += (float) $ant['debe'];
            $totales['anterior']['haber']          += (float) $ant['haber'];
            $totales['anterior']['saldo_deudor']   += $antSaldo['saldo_deudor'];
            $totales['anterior']['saldo_acreedor'] += $antSaldo['saldo_acreedor'];

            $totales['mensual']['debe']           += (float) $men['debe'];
            $totales['mensual']['haber']          += (float) $men['haber'];
            $totales['mensual']['saldo_deudor']   += $menSaldo['saldo_deudor'];
            $totales['mensual']['saldo_acreedor'] += $menSaldo['saldo_acreedor'];

            $totales['acumulado']['debe']           += $acumDebe;
            $totales['acumulado']['haber']          += $acumHaber;
            $totales['acumulado']['saldo_deudor']   += $acumSaldo['saldo_deudor'];
            $totales['acumulado']['saldo_acreedor'] += $acumSaldo['saldo_acreedor'];
        }

        foreach ($totales as $sec => $vals) {
            foreach ($vals as $k => $v) {
                $totales[$sec][$k] = round($v, 2);
            }
        }

        return [
            'periodo' => [
                'inicio'      => $fechaInicio,
                'fin'         => $fechaFin,
                'inicio_anio' => $inicioAnio,
            ],
            'cuentas' => $cuentas,
            'totales' => $totales,
        ];
    }

    private function consultarBalancePeriodo(int $empresaId, string $fechaDesde, string $fechaHasta, int $filtro): array
    {
        $query = DetalleAsiento::join('asientos_contables', 'detalles_asiento.asiento_id', '=', 'asientos_contables.id')
            ->join('plan_cuentas', function ($join) {
                $join->on('detalles_asiento.cuenta_contable', '=', 'plan_cuentas.codigo')
                    ->on('plan_cuentas.empresa_id', '=', 'asientos_contables.empresa_id');
            })
            ->where('asientos_contables.empresa_id', $empresaId)
            ->whereBetween('asientos_contables.fecha', [$fechaDesde, $fechaHasta]);

        $query = $this->aplicarFiltroEstado($query, $filtro, 'asientos_contables.estado');

        $filas = $query
            ->groupBy('detalles_asiento.cuenta_contable', 'plan_cuentas.nombre', 'plan_cuentas.tipo')
            ->orderBy('detalles_asiento.cuenta_contable', 'asc')
            ->selectRaw(
                'detalles_asiento.cuenta_contable as codigo,
                 plan_cuentas.nombre,
                 plan_cuentas.tipo,
                 COALESCE(SUM(detalles_asiento.debe), 0) as total_debe,
                 COALESCE(SUM(detalles_asiento.haber), 0) as total_haber'
            )
            ->get();

        $map = [];
        foreach ($filas as $fila) {
            $map[$fila->codigo] = [
                'nombre' => $fila->nombre,
                'tipo'   => $fila->tipo,
                'debe'   => (float) $fila->total_debe,
                'haber'  => (float) $fila->total_haber,
            ];
        }

        return $map;
    }

    private function calcularSaldoBalance(float $debe, float $haber): array
    {
        if ($debe >= $haber) {
            return [
                'debe'           => round($debe, 2),
                'haber'          => round($haber, 2),
                'saldo_deudor'   => round($debe - $haber, 2),
                'saldo_acreedor' => 0.0,
            ];
        }
        return [
            'debe'           => round($debe, 2),
            'haber'          => round($haber, 2),
            'saldo_deudor'   => 0.0,
            'saldo_acreedor' => round($haber - $debe, 2),
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