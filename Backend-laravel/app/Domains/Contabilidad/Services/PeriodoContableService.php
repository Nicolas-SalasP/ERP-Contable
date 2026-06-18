<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Exceptions\PeriodoCerradoException;
use App\Domains\Contabilidad\Models\PeriodoContable;
use App\Domains\Core\Models\Auditoria;
use App\Domains\Core\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gestion de bloqueo de periodos contables (inmutabilidad). El cierre es una accion
 * MANUAL y explicita; un periodo cerrado rechaza toda escritura contable con fecha
 * dentro de el. La reapertura es privilegiada (jerarquia >= 80) y queda auditada.
 */
class PeriodoContableService
{
    /** Jerarquia minima de rol para reabrir un periodo cerrado. */
    public const JERARQUIA_REABRIR = 80;

    public function estaCerrado(int $empresaId, string|Carbon $fecha): bool
    {
        [$anio, $mes] = $this->descomponer($fecha);

        return PeriodoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', PeriodoContable::ESTADO_CERRADO)
            ->exists();
    }

    /**
     * Garantia central: lanza PeriodoCerradoException (HTTP 409) si la fecha cae en
     * un periodo cerrado. Invocado por el observer y por los guards de servicio.
     */
    public function assertAbierto(int $empresaId, string|Carbon $fecha): void
    {
        if ($this->estaCerrado($empresaId, $fecha)) {
            [$anio, $mes] = $this->descomponer($fecha);
            throw new PeriodoCerradoException($anio, $mes);
        }
    }

    public function cerrar(int $empresaId, int $anio, int $mes, User $usuario, ?string $motivo = null): PeriodoContable
    {
        $this->validarMesAnio($anio, $mes);

        $periodo = PeriodoContable::query()->firstOrNew([
            'empresa_id' => $empresaId,
            'anio' => $anio,
            'mes' => $mes,
        ]);

        if ($periodo->exists && $periodo->estado === PeriodoContable::ESTADO_CERRADO) {
            return $periodo; // idempotente
        }

        $periodo->fill([
            'estado' => PeriodoContable::ESTADO_CERRADO,
            'cerrado_por' => $usuario->id,
            'cerrado_at' => now(),
            'reabierto_por' => null,
            'reabierto_at' => null,
            'motivo' => $motivo,
        ])->save();

        $this->auditar($periodo, $usuario, 'CIERRE_PERIODO', $motivo);

        return $periodo;
    }

    public function reabrir(int $empresaId, int $anio, int $mes, User $usuario, string $motivo): PeriodoContable
    {
        if ((int) ($usuario->rol?->jerarquia ?? 0) < self::JERARQUIA_REABRIR) {
            throw new AuthorizationException('No tienes jerarquia suficiente para reabrir un periodo contable.');
        }

        if (trim($motivo) === '') {
            throw new AuthorizationException('Debes indicar un motivo para reabrir el periodo.');
        }

        $periodo = PeriodoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', PeriodoContable::ESTADO_CERRADO)
            ->first();

        if (!$periodo) {
            throw new AuthorizationException('El periodo indicado no esta cerrado.');
        }

        $periodo->fill([
            'estado' => PeriodoContable::ESTADO_ABIERTO,
            'reabierto_por' => $usuario->id,
            'reabierto_at' => now(),
            'motivo' => $motivo,
        ])->save();

        $this->auditar($periodo, $usuario, 'REAPERTURA_PERIODO', $motivo);

        return $periodo;
    }

    /** Lista los periodos cerrados de una empresa (opcionalmente filtrando por anio). */
    public function listarCerrados(int $empresaId, ?int $anio = null): Collection
    {
        return PeriodoContable::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', PeriodoContable::ESTADO_CERRADO)
            ->when($anio !== null, fn ($q) => $q->where('anio', $anio))
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();
    }

    /**
     * @return array{anio:int, mes:int}
     */
    private function descomponer(string|Carbon $fecha): array
    {
        $c = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);

        return [(int) $c->year, (int) $c->month];
    }

    private function validarMesAnio(int $anio, int $mes): void
    {
        if ($mes < 1 || $mes > 12) {
            throw new \InvalidArgumentException("Mes invalido: {$mes}");
        }

        if ($anio < 2000 || $anio > 2100) {
            throw new \InvalidArgumentException("Anio invalido: {$anio}");
        }
    }

    private function auditar(PeriodoContable $periodo, User $usuario, string $operacion, ?string $motivo): void
    {
        Auditoria::create([
            'auditable_type' => PeriodoContable::class,
            'auditable_id' => $periodo->id,
            'nombre_usuario' => $usuario->nombre ?? (string) $usuario->id,
            'operacion' => $operacion,
            'estado_anterior' => $operacion === 'CIERRE_PERIODO' ? PeriodoContable::ESTADO_ABIERTO : PeriodoContable::ESTADO_CERRADO,
            'estado_nuevo' => $periodo->estado,
            'detalle' => sprintf('Periodo %02d/%d. %s', $periodo->mes, $periodo->anio, $motivo ?? ''),
            'referencia_cruzada' => "periodo:{$periodo->empresa_id}:{$periodo->anio}:{$periodo->mes}",
        ]);
    }
}
