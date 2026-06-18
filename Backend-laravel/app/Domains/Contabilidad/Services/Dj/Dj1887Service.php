<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Core\Models\Empresa;
use App\Domains\Rrhh\Models\Liquidacion;

class Dj1887Service implements DeclaracionJuradaContract
{
    public function codigo(): string
    {
        return '1887';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $empresa = Empresa::findOrFail($empresaId);

        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->whereIn('estado', [Liquidacion::ESTADO_EMITIDA, 'PAGADA'])
            ->with(['empleado', 'contrato', 'detalles'])
            ->orderBy('empleado_id')
            ->orderBy('mes')
            ->get();

        // Agrupar por empleado para líneas anuales
        $porEmpleado = $liquidaciones->groupBy('empleado_id');

        $lineas           = [];
        $numeroCertificado = 1;

        foreach ($porEmpleado as $empleadoId => $liqsEmpleado) {
            $primerLiq = $liqsEmpleado->first();
            $empleado  = $primerLiq->empleado;

            // Totales anuales (MVP: sin actualización por IPC)
            $rentaTotalNeta = (int) $liqsEmpleado->sum('base_tributable');
            $iuscRetenido   = 0;
            $rentaNoGravada = (int) $liqsEmpleado->sum('total_haberes_no_imponibles');

            foreach ($liqsEmpleado as $liq) {
                $iuscDetalle = $liq->detalles
                    ->filter(fn ($d) => $d->tipo === 'DESCUENTO_LEGAL'
                        && str_contains(strtoupper($d->codigo_concepto), 'IMPUESTO'))
                    ->sum('monto');
                $iuscRetenido += (int) $iuscDetalle;
            }

            // Detalle mensual: horas semanales se resuelven por liquidación individual
            // para capturar cambios de jornada durante el año.
            $mesesDetalle = [];
            foreach ($liqsEmpleado as $liq) {
                $contratoMes = $liq->contrato;
                $horasMes    = $contratoMes?->horas_semana ?? 99;
                if (($contratoMes?->tipo_jornada ?? '') === 'EXCEPTUADO') {
                    $horasMes = 99;
                }

                $mesesDetalle[] = [
                    'mes'             => $liq->mes,
                    'codigo_periodo'  => $this->resolverCodigoPeriodo($liq),
                    'monto_mensual'   => (int) $liq->base_tributable,
                    'horas_semanales' => $horasMes,
                    'num_certificado' => $numeroCertificado,
                ];
            }

            // Horas semanales del registro D: último mes con liquidación (diciembre o el más reciente)
            $ultimoContrato   = $liqsEmpleado->last()->contrato;
            $horasSemanales   = $ultimoContrato?->horas_semana ?? 99;
            if (($ultimoContrato?->tipo_jornada ?? '') === 'EXCEPTUADO') {
                $horasSemanales = 99;
            }

            $lineas[] = new DjLineaData([
                'rut_trabajador'        => $empleado->rut,
                'renta_total_neta'      => $rentaTotalNeta,
                'iusc_retenido'         => $iuscRetenido,
                'mayor_retencion'       => 0,
                'renta_no_gravada'      => $rentaNoGravada,
                'renta_exenta'          => 0,
                'rebaja_zonas_extremas' => 0,
                'prestamo_3pct'         => 0,
                'horas_semanales'       => $horasSemanales,
                'num_certificado'       => $numeroCertificado,
                'meses'                 => $mesesDetalle,
            ]);

            $numeroCertificado++;
        }

        return new DjData(
            codigoDj:  '1887',
            empresaId: $empresaId,
            anio:      $anio,
            cabecera: [
                'rut_empresa'  => $empresa->rut,
                'razon_social' => $empresa->razon_social,
                'correo'       => $empresa->email ?? '',
                'telefono'     => $empresa->telefono ?? '',
            ],
            lineas: $lineas,
        );
    }

    public function validar(DjData $data): array
    {
        $errores = [];

        if (empty($data->cabecera['rut_empresa'])) {
            $errores[] = 'RUT de empresa vacío.';
        }

        if (empty($data->lineas)) {
            $errores[] = 'No hay trabajadores con rentas en el período declarado.';
        }

        foreach ($data->lineas as $i => $linea) {
            $n = $i + 1;
            if (empty($linea->campos['rut_trabajador'])) {
                $errores[] = "Trabajador #{$n}: RUT vacío.";
            }
            if (($linea->campos['renta_total_neta'] ?? 0) <= 0) {
                $errores[] = "Trabajador #{$n} ({$linea->campos['rut_trabajador']}): renta neta es cero o negativa.";
            }
            if (empty($linea->campos['meses'])) {
                $errores[] = "Trabajador #{$n}: sin períodos mensuales.";
            }
        }

        return $errores;
    }

    public function formatearArchivo(DjData $data): string
    {
        $lineas = [];

        // Cabecera del archivo
        $lineas[] = implode(';', [
            'A',
            $data->cabecera['rut_empresa'],
            $data->cabecera['razon_social'],
            $data->anio,
            $data->cabecera['correo'],
            $data->cabecera['telefono'],
        ]);

        // Detalle por trabajador
        foreach ($data->lineas as $linea) {
            $c = $linea->campos;

            // Registro anual del trabajador
            $lineas[] = implode(';', [
                'D',
                $c['rut_trabajador'],
                $c['renta_total_neta'],
                $c['iusc_retenido'],
                $c['mayor_retencion'],
                $c['renta_no_gravada'],
                $c['renta_exenta'],
                $c['rebaja_zonas_extremas'],
                $c['prestamo_3pct'],
                $c['horas_semanales'],
                $c['num_certificado'],
            ]);

            // Registros mensuales
            foreach ($c['meses'] as $mes) {
                $lineas[] = implode(';', [
                    'M',
                    $c['rut_trabajador'],
                    str_pad((string) $mes['mes'], 2, '0', STR_PAD_LEFT),
                    $mes['codigo_periodo'],
                    $mes['monto_mensual'],
                    $mes['horas_semanales'],
                    $mes['num_certificado'],
                ]);
            }
        }

        // Totalizador global
        $totalRenta = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'renta_total_neta'));
        $totalIusc  = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'iusc_retenido'));
        $totalCasos = count($data->lineas);

        $lineas[] = implode(';', ['T', $totalCasos, $totalRenta, $totalIusc]);

        return implode("\n", $lineas) . "\n";
    }

    /**
     * Determina el código de período SII para una liquidación:
     *  C  → contrato completo (normal)
     *  P  → jornada parcial
     *  G  → gerente / jornada exceptuada
     *  F  → finiquitado en ese mes
     */
    private function resolverCodigoPeriodo(Liquidacion $liq): string
    {
        $contrato = $liq->contrato;

        if (! $contrato) {
            return 'C';
        }

        // Trabajador finiquitado en este mismo período
        if ($contrato->fecha_termino_real) {
            $terminoMes  = (int) date('n', strtotime($contrato->fecha_termino_real));
            $terminoAnio = (int) date('Y', strtotime($contrato->fecha_termino_real));
            if ($terminoAnio === $liq->anio && $terminoMes === $liq->mes) {
                return 'F';
            }
        }

        return match ($contrato->tipo_jornada ?? 'COMPLETA') {
            'PARCIAL'    => 'P',
            'EXCEPTUADO' => 'G',
            default      => 'C',
        };
    }
}
