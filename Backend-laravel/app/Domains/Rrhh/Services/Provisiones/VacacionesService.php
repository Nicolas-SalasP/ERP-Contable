<?php

namespace App\Domains\Rrhh\Services\Provisiones;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\ProvisionVacaciones;
use App\Domains\Rrhh\Models\SolicitudVacaciones;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Devengo mensual de vacaciones (Art. 67 CdT): 1,25 días hábiles/mes (15 anuales ÷ 12); vacaciones progresivas (Art. 68) aplican con 10+ años de servicio. */
class VacacionesService
{
    // Días hábiles de vacaciones por año según ley (base Art. 67)
    private const DIAS_HABILES_ANUALES = 15.0;

    public function devengarMes(int $empresaId, int $liquidacionId): ProvisionVacaciones
    {
        return DB::transaction(function () use ($empresaId, $liquidacionId) {
            $liq = \App\Domains\Rrhh\Models\Liquidacion::where('empresa_id', $empresaId)
                ->with(['empleado', 'contrato'])
                ->findOrFail($liquidacionId);

            /** @var \App\Domains\Rrhh\Models\Contrato $contrato */
            $contrato = $liq->contrato;
            /** @var \App\Domains\Rrhh\Models\Empleado $empleado */
            $empleado = $liq->empleado;
            $anio = $liq->anio;
            $mes = $liq->mes;

            $diasAnuales = $this->diasAnualesSegunAntigüedad($contrato);
            $diasMes = round($diasAnuales / 12, 4);

            // Remuneración diaria (base para provisión)
            $remDiaria = round((float) $contrato->sueldo_base / 30, 2);

            $montoMes = round($remDiaria * $diasMes, 2);

            // Buscar saldo anterior
            $anterior = ProvisionVacaciones::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleado->id)
                ->where(function ($q) use ($anio, $mes) {
                    $q->where('anio', $anio)->where('mes', '<', $mes)
                        ->orWhere('anio', '<', $anio);
                })
                ->orderByDesc('anio')
                ->orderByDesc('mes')
                ->first();

            $saldoAnterior = $anterior ? (float) $anterior->saldo_dias_habiles : 0.0;
            $montoAnterior = $anterior ? (float) $anterior->monto_provisionado_total : 0.0;

            return ProvisionVacaciones::updateOrCreate(
                ['empresa_id' => $empresaId, 'empleado_id' => $empleado->id, 'anio' => $anio, 'mes' => $mes],
                [
                    'contrato_id' => $contrato->id,
                    'dias_devengados_mes' => $diasMes,
                    'saldo_dias_habiles' => round($saldoAnterior + $diasMes, 4),
                    'monto_devengado_mes' => $montoMes,
                    'monto_provisionado_total' => round($montoAnterior + $montoMes, 2),
                    'remuneracion_diaria' => $remDiaria,
                ]
            );
        });
    }

    public function saldoActual(int $empresaId, int $empleadoId): array
    {
        $ultimo = ProvisionVacaciones::where('empresa_id', $empresaId)
            ->where('empleado_id', $empleadoId)
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        $diasDevengados = $ultimo ? (float) $ultimo->saldo_dias_habiles : 0.0;
        $diasTomados = $this->diasTomados($empresaId, $empleadoId);

        return [
            'dias_devengados' => $diasDevengados,
            'dias_tomados' => $diasTomados,
            'dias_disponibles' => round($diasDevengados - $diasTomados, 4),
            'monto_provisionado' => $ultimo ? (float) $ultimo->monto_provisionado_total : 0.0,
            'ultimo_periodo' => $ultimo ? "{$ultimo->mes}/{$ultimo->anio}" : null,
        ];
    }

    private function diasTomados(int $empresaId, int $empleadoId): float
    {
        return (float) SolicitudVacaciones::where('empresa_id', $empresaId)
            ->where('empleado_id', $empleadoId)
            ->where('estado', SolicitudVacaciones::ESTADO_APROBADA)
            ->sum('dias_habiles');
    }

    /** Cuenta días hábiles (lunes a viernes) entre dos fechas, ambas incluidas. */
    public function diasHabilesEntre(Carbon $desde, Carbon $hasta): int
    {
        if ($hasta->lt($desde)) {
            return 0;
        }

        $dias = 0;
        $cursor = $desde->copy();
        while ($cursor->lte($hasta)) {
            if ($cursor->isWeekday()) {
                $dias++;
            }
            $cursor->addDay();
        }

        return $dias;
    }

    /** Crea una solicitud PENDIENTE; no descuenta saldo aún (el descuento ocurre al aprobar, saldoActual solo suma APROBADA), así que solicitudes simultáneas del mismo empleado no se bloquean entre sí hasta que una se aprueba. */
    public function solicitar(int $empresaId, int $empleadoId, string $fechaDesde, string $fechaHasta, int $userId, ?string $observacion = null): SolicitudVacaciones
    {
        return DB::transaction(function () use ($empresaId, $empleadoId, $fechaDesde, $fechaHasta, $userId, $observacion) {
            $empleado = Empleado::where('empresa_id', $empresaId)->find($empleadoId);
            if (!$empleado) {
                throw RrhhException::noEncontrado('El empleado no existe o no pertenece a la empresa.');
            }

            $contrato = Contrato::where('empresa_id', $empresaId)
                ->where('empleado_id', $empleadoId)
                ->where('estado', 'VIGENTE')
                ->orderByDesc('fecha_inicio')
                ->first();

            if (!$contrato) {
                throw RrhhException::regla('El empleado no tiene un contrato vigente.');
            }

            $desde = Carbon::parse($fechaDesde)->startOfDay();
            $hasta = Carbon::parse($fechaHasta)->startOfDay();

            if ($hasta->lt($desde)) {
                throw RrhhException::regla('La fecha hasta no puede ser anterior a la fecha desde.');
            }

            if ($contrato->fecha_termino && $hasta->gt($contrato->fecha_termino)) {
                throw RrhhException::regla(
                    "El rango solicitado excede la fecha de termino del contrato ({$contrato->fecha_termino->toDateString()})."
                );
            }

            $diasHabiles = $this->diasHabilesEntre($desde, $hasta);
            if ($diasHabiles <= 0) {
                throw RrhhException::regla('El rango seleccionado no contiene dias habiles.');
            }

            $saldo = $this->saldoActual($empresaId, $empleadoId);
            if ($diasHabiles > $saldo['dias_disponibles']) {
                throw RrhhException::regla(
                    "Saldo insuficiente: solicita {$diasHabiles} dias habiles y el saldo disponible es " .
                    "{$saldo['dias_disponibles']}."
                );
            }

            return SolicitudVacaciones::create([
                'empresa_id' => $empresaId,
                'empleado_id' => $empleadoId,
                'contrato_id' => $contrato->id,
                'fecha_desde' => $desde->toDateString(),
                'fecha_hasta' => $hasta->toDateString(),
                'dias_habiles' => $diasHabiles,
                'estado' => SolicitudVacaciones::ESTADO_PENDIENTE,
                'observacion' => $observacion,
                'solicitado_por' => $userId,
            ]);
        });
    }

    /** Aprueba una solicitud PENDIENTE, revalidando el saldo con lock para evitar que dos solicitudes del mismo empleado se aprueben en paralelo y dejen el saldo en negativo. */
    public function aprobar(int $empresaId, int $solicitudId, int $userId): SolicitudVacaciones
    {
        return DB::transaction(function () use ($empresaId, $solicitudId, $userId) {
            $solicitud = SolicitudVacaciones::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($solicitudId);

            if (!$solicitud) {
                throw RrhhException::noEncontrado('La solicitud no existe o no pertenece a la empresa.');
            }

            if ($solicitud->estado !== SolicitudVacaciones::ESTADO_PENDIENTE) {
                throw RrhhException::regla("La solicitud ya fue resuelta (estado {$solicitud->estado}).");
            }

            // lockForUpdate() de arriba solo bloquea ESTA solicitud; se bloquea además la fila del empleado como mutex compartido para serializar cualquier aprobación concurrente del mismo empleado, sin importar qué solicitud estén tocando.
            Empleado::where('empresa_id', $empresaId)->where('id', $solicitud->empleado_id)->lockForUpdate()->first();

            $saldo = $this->saldoActual($empresaId, $solicitud->empleado_id);
            if ((float) $solicitud->dias_habiles > $saldo['dias_disponibles']) {
                throw RrhhException::regla(
                    "Saldo insuficiente al momento de aprobar: {$solicitud->dias_habiles} dias solicitados, " .
                    "{$saldo['dias_disponibles']} disponibles."
                );
            }

            $solicitud->update([
                'estado' => SolicitudVacaciones::ESTADO_APROBADA,
                'resuelto_por' => $userId,
                'resuelto_at' => now(),
            ]);

            return $solicitud->fresh();
        });
    }

    public function rechazar(int $empresaId, int $solicitudId, int $userId, string $motivo): SolicitudVacaciones
    {
        return DB::transaction(function () use ($empresaId, $solicitudId, $userId, $motivo) {
            $solicitud = SolicitudVacaciones::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($solicitudId);

            if (!$solicitud) {
                throw RrhhException::noEncontrado('La solicitud no existe o no pertenece a la empresa.');
            }

            if ($solicitud->estado !== SolicitudVacaciones::ESTADO_PENDIENTE) {
                throw RrhhException::regla("La solicitud ya fue resuelta (estado {$solicitud->estado}).");
            }

            $solicitud->update([
                'estado' => SolicitudVacaciones::ESTADO_RECHAZADA,
                'motivo_rechazo' => $motivo,
                'resuelto_por' => $userId,
                'resuelto_at' => now(),
            ]);

            return $solicitud->fresh();
        });
    }

    /** Anula una solicitud APROBADA por error (repone el saldo, ya que saldoActual solo suma solicitudes en estado APROBADA). */
    public function anular(int $empresaId, int $solicitudId, int $userId, string $motivo): SolicitudVacaciones
    {
        return DB::transaction(function () use ($empresaId, $solicitudId, $userId, $motivo) {
            $solicitud = SolicitudVacaciones::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($solicitudId);

            if (!$solicitud) {
                throw RrhhException::noEncontrado('La solicitud no existe o no pertenece a la empresa.');
            }

            if ($solicitud->estado !== SolicitudVacaciones::ESTADO_APROBADA) {
                throw RrhhException::regla('Solo se puede anular una solicitud APROBADA.');
            }

            $solicitud->update([
                'estado' => SolicitudVacaciones::ESTADO_ANULADA,
                'motivo_anulacion' => $motivo,
                'resuelto_por' => $userId,
                'resuelto_at' => now(),
            ]);

            return $solicitud->fresh();
        });
    }

    public function listarSolicitudes(int $empresaId, array $filtros = [])
    {
        $query = SolicitudVacaciones::where('empresa_id', $empresaId)
            ->with(['empleado:id,nombres,apellido_paterno,apellido_materno,rut'])
            ->orderByDesc('created_at');

        if (!empty($filtros['empleado_id'])) {
            $query->where('empleado_id', (int) $filtros['empleado_id']);
        }
        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $query->paginate(30);
    }

    /** Calcula días proporcionales y monto de feriado al término del contrato (Art. 70): usa $fechaTermino como referencia del aniversario (no now()) y convierte días hábiles a corridos para la valorización; la base sigue siendo sueldo_base/30, ampliar a remuneración íntegra (Art. 71) queda fuera de alcance. */
    public function calcularVacacionesProporcionales(Contrato $contrato, string $fechaTermino): array
    {
        $fechaInicio = $contrato->fecha_inicio;
        $fechaFin = Carbon::parse($fechaTermino);

        // Aniversario relativo a la fecha de término (no a now())
        $aniversario = $fechaInicio->copy()->year($fechaFin->year);
        if ($aniversario->gt($fechaFin)) {
            $aniversario->subYear();
        }

        $diasDesdeAniversario = $aniversario->diffInDays($fechaFin);
        $diasAnuales = $this->diasAnualesSegunAntigüedad($contrato, $fechaFin);
        $diasHabilesGanados = round(($diasDesdeAniversario / 365) * $diasAnuales, 4);

        // Convertir días hábiles a días corridos contando desde el día siguiente al término
        $diasCorridos = $this->habilesACorridos((int) round($diasHabilesGanados), $fechaFin);

        $remDiaria = (float) $contrato->sueldo_base / 30;
        $monto = round($diasCorridos * $remDiaria, 2);

        return [
            'dias' => $diasHabilesGanados,
            'dias_corridos' => $diasCorridos,
            'monto' => $monto,
            'remuneracion_diaria' => $remDiaria,
        ];
    }

    /** Convierte N días hábiles a días corridos, contando hacia adelante desde el día siguiente a $fechaBase y saltando sábados y domingos (los fines de semana intercalados quedan incluidos en el conteo corrido). */
    public function habilesACorridos(int $diasHabiles, Carbon $fechaBase): int
    {
        if ($diasHabiles <= 0) {
            return 0;
        }

        $cursor = $fechaBase->copy()->addDay();
        $habilesContados = 0;
        $inicio = $cursor->copy();

        while ($habilesContados < $diasHabiles) {
            if ($cursor->isWeekday()) {
                $habilesContados++;
            }
            if ($habilesContados < $diasHabiles) {
                $cursor->addDay();
            }
        }

        return (int) $inicio->diffInDays($cursor) + 1;
    }

    private function diasAnualesSegunAntigüedad(Contrato $contrato, ?Carbon $referencia = null): float
    {
        $ref = $referencia ?? Carbon::now();
        $fechaInicio = $contrato->fecha_inicio;

        // Vacaciones progresivas Art. 68: 10+ años → 1 día extra c/3 años adicionales
        $anios = (int) $fechaInicio->diffInYears($ref);
        if ($anios < 10) {
            return self::DIAS_HABILES_ANUALES;
        }
        $extra = (int) floor(($anios - 10) / 3);
        return self::DIAS_HABILES_ANUALES + $extra;
    }
}
