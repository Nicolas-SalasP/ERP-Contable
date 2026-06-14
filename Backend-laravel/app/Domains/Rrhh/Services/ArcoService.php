<?php

namespace App\Domains\Rrhh\Services;

use App\Domains\Core\Models\SolicitudArco;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5 — Ley 21.719: ejercicio de derechos ARCO+ (Acceso, Rectificación,
 * Cancelación/Supresión, Oposición/Bloqueo y Portabilidad) sobre titulares
 * empleados.
 *
 * El derecho de supresión convive con la obligación legal de retención de los
 * registros de remuneraciones/tributarios (~6 años, ver config/arco.php):
 *
 *  - Bajo retención: NO se elimina la identidad. Se bloquea el tratamiento y se
 *    eliminan únicamente los datos NO esenciales (contacto). La supresión total
 *    queda pendiente hasta el vencimiento del plazo.
 *  - Vencido el plazo: anonimización total + soft delete del empleado. Las
 *    liquidaciones/contratos se conservan pero ya no portan identidad
 *    descifrable.
 */
class ArcoService
{
    /**
     * Exporta los datos personales del empleado (derecho de acceso/portabilidad).
     * Devuelve un arreglo estructurado con la identidad y contacto descifrados,
     * los datos bancarios enmascarados (sólo últimos 4 dígitos), contratos,
     * cargas familiares y resumen de liquidaciones.
     */
    public function exportarEmpleado(int $empresaId, int $id): array
    {
        $empleado = $this->resolver($empresaId, $id);

        $datos = [
            'identidad' => [
                'rut'              => $empleado->rut,
                'nombres'          => $empleado->nombres,
                'apellido_paterno' => $empleado->apellido_paterno,
                'apellido_materno' => $empleado->apellido_materno,
                'fecha_nacimiento' => optional($empleado->fecha_nacimiento)->toDateString(),
                'sexo'             => $empleado->sexo,
                'nacionalidad'     => $empleado->nacionalidad,
                'estado_civil'     => $empleado->estado_civil,
            ],
            'contacto' => [
                'email'     => $empleado->email,
                'telefono'  => $empleado->telefono,
                'direccion' => $empleado->direccion,
                'ciudad'    => $empleado->ciudad,
                'region'    => $empleado->region,
            ],
            'banco' => [
                'banco_nombre'      => $empleado->banco_nombre,
                'banco_tipo_cuenta' => $empleado->banco_tipo_cuenta,
                'numero_cuenta'     => $this->enmascararCuenta($empleado->banco_numero_cuenta),
            ],
            'contratos' => $empleado->contratos->map(fn ($c) => [
                'id'                 => $c->id,
                'tipo'               => $c->tipo,
                'cargo'              => $c->cargo,
                'fecha_inicio'       => optional($c->fecha_inicio)->toDateString(),
                'fecha_termino'      => optional($c->fecha_termino)->toDateString(),
                'fecha_termino_real' => optional($c->fecha_termino_real)->toDateString(),
                'estado'             => $c->estado,
                'es_contrato_activo' => $c->es_contrato_activo,
            ])->all(),
            'cargas_familiares' => $empleado->cargasFamiliares->map(fn ($cf) => [
                'rut'    => $cf->rut,
                'nombre' => $cf->nombre,
                'tipo'   => $cf->tipo,
            ])->all(),
            'liquidaciones' => $empleado->liquidaciones()
                ->orderBy('anio')
                ->orderBy('mes')
                ->get(['anio', 'mes', 'liquido_a_pagar'])
                ->map(fn ($l) => [
                    'periodo'         => sprintf('%04d-%02d', $l->anio, $l->mes),
                    'anio'            => $l->anio,
                    'mes'             => $l->mes,
                    'liquido_a_pagar' => $l->liquido_a_pagar,
                ])->all(),
        ];

        $this->registrar(
            $empleado,
            SolicitudArco::TIPO_ACCESO,
            SolicitudArco::ESTADO_COMPLETADA,
            null,
            'Exportación de datos personales del titular (derecho de acceso).'
        );

        return $datos;
    }

    /**
     * Bloquea el tratamiento de los datos del empleado (derecho de oposición/bloqueo).
     */
    public function bloquearEmpleado(int $empresaId, int $id, ?string $motivo): Empleado
    {
        return DB::transaction(function () use ($empresaId, $id, $motivo) {
            $empleado = $this->resolver($empresaId, $id);

            $empleado->bloqueado_at = now();
            $empleado->save();

            $this->registrar(
                $empleado,
                SolicitudArco::TIPO_BLOQUEO,
                SolicitudArco::ESTADO_COMPLETADA,
                $motivo,
                'Tratamiento bloqueado a solicitud del titular.'
            );

            return $empleado->fresh();
        });
    }

    /**
     * Suprime/anonimiza los datos del empleado (derecho de supresión/cancelación).
     *
     * Si el titular está bajo retención legal, ejecuta una supresión PARCIAL
     * (bloqueo + borrado de datos no esenciales, conservando la identidad mínima).
     * Vencido el plazo, ejecuta la anonimización TOTAL + soft delete.
     *
     * @return array{modo: 'parcial'|'total', mensaje: string}
     */
    public function suprimirEmpleado(int $empresaId, int $id, ?string $motivo): array
    {
        return DB::transaction(function () use ($empresaId, $id, $motivo) {
            $empleado = $this->resolver($empresaId, $id);

            if ($this->estaBajoRetencion($empleado)) {
                return $this->suprimirParcial($empleado, $motivo);
            }

            return $this->suprimirTotal($empleado, $motivo);
        });
    }

    /**
     * Determina si el titular está bajo retención legal obligatoria.
     *
     * Está bajo retención si se cumple CUALQUIERA de:
     *  - tiene un contrato activo (es_contrato_activo, estado VIGENTE/SUSPENDIDO,
     *    o sin fecha_termino_real);
     *  - tiene una liquidación cuyo período (anio, mes) cae dentro de la ventana
     *    de retención;
     *  - tiene un contrato cuya fecha_termino o fecha_termino_real cae dentro de
     *    la ventana de retención.
     */
    public function estaBajoRetencion(Empleado $empleado): bool
    {
        $anios = (int) config('arco.retencion_anios', 6);
        $limite = now()->copy()->subYears($anios);

        // Contrato activo
        $contratoActivo = $empleado->contratos()
            ->where(function ($q) {
                $q->where('es_contrato_activo', true)
                    ->orWhereIn('estado', ['VIGENTE', 'SUSPENDIDO'])
                    ->orWhereNull('fecha_termino_real');
            })
            ->exists();

        if ($contratoActivo) {
            return true;
        }

        // Empleado en estado activo
        if (in_array($empleado->estado, ['ACTIVO', 'LICENCIA'], true)) {
            return true;
        }

        // Liquidación dentro de la ventana de retención
        $liquidacionReciente = $empleado->liquidaciones()
            ->where(function ($q) use ($limite) {
                $q->where('anio', '>', $limite->year)
                    ->orWhere(function ($qq) use ($limite) {
                        $qq->where('anio', $limite->year)
                            ->where('mes', '>=', $limite->month);
                    });
            })
            ->exists();

        if ($liquidacionReciente) {
            return true;
        }

        // Contrato cuya terminación cae dentro de la ventana de retención
        $contratoReciente = $empleado->contratos()
            ->where(function ($q) use ($limite) {
                $q->where('fecha_termino', '>=', $limite->toDateString())
                    ->orWhere('fecha_termino_real', '>=', $limite->toDateString());
            })
            ->exists();

        return $contratoReciente;
    }

    /**
     * Supresión parcial bajo retención: bloquea el tratamiento y borra los datos
     * NO esenciales (contacto), conservando la identidad mínima exigida por ley.
     *
     * @return array{modo: 'parcial', mensaje: string}
     */
    private function suprimirParcial(Empleado $empleado, ?string $motivo): array
    {
        $empleado->email = null;
        $empleado->telefono = null;
        $empleado->direccion = null;
        $empleado->bloqueado_at = now();
        $empleado->save();

        $resultado = 'Supresión parcial: el titular está bajo retención legal '
            . 'obligatoria (registros de remuneraciones/tributarios). Se bloqueó el '
            . 'tratamiento y se eliminaron los datos no esenciales (contacto). '
            . 'La supresión total queda PENDIENTE hasta el vencimiento del plazo de retención.';

        $this->registrar(
            $empleado,
            SolicitudArco::TIPO_SUPRESION,
            SolicitudArco::ESTADO_PARCIAL_RETENCION,
            $motivo,
            $resultado
        );

        return [
            'modo'    => 'parcial',
            'mensaje' => $resultado,
        ];
    }

    /**
     * Supresión total vencido el plazo: anonimización irreversible de la identidad
     * y datos del titular + soft delete. Las liquidaciones/contratos se conservan
     * pero ya no portan identidad descifrable.
     *
     * @return array{modo: 'total', mensaje: string}
     */
    private function suprimirTotal(Empleado $empleado, ?string $motivo): array
    {
        // El token ANON-{id} es único por empleado: preserva la unicidad del
        // blind index del RUT tras la anonimización (CipherSweet).
        $empleado->rut = 'ANON-' . $empleado->id;
        $empleado->nombres = 'ANONIMIZADO';
        $empleado->apellido_paterno = null;
        $empleado->apellido_materno = null;
        $empleado->fecha_nacimiento = null;
        $empleado->email = null;
        $empleado->telefono = null;
        $empleado->direccion = null;
        $empleado->banco_numero_cuenta_cifrado = null;
        $empleado->anonimizado_at = now();
        $empleado->save();

        // Anonimiza las cargas familiares del titular (datos de terceros vinculados).
        foreach ($empleado->cargasFamiliares()->withoutGlobalScopes()->get() as $carga) {
            $carga->rut = null;
            $carga->nombre = 'ANONIMIZADO';
            $carga->save();
        }

        $empleado->delete();

        $resultado = 'Supresión total: vencido el plazo de retención, se anonimizó '
            . 'irreversiblemente la identidad y los datos del titular (y sus cargas '
            . 'familiares), y se eliminó la ficha. Los registros de remuneraciones '
            . 'se conservan sin identidad descifrable.';

        $this->registrar(
            $empleado,
            SolicitudArco::TIPO_SUPRESION,
            SolicitudArco::ESTADO_COMPLETADA,
            $motivo,
            $resultado
        );

        return [
            'modo'    => 'total',
            'mensaje' => $resultado,
        ];
    }

    /**
     * Resuelve el empleado dentro del alcance de la empresa o lanza 404.
     */
    private function resolver(int $empresaId, int $id): Empleado
    {
        $empleado = Empleado::where('empresa_id', $empresaId)
            ->with(['contratos', 'cargasFamiliares'])
            ->find($id);

        if (! $empleado) {
            throw RrhhException::noEncontrado('El empleado no existe o no pertenece a la empresa.');
        }

        return $empleado;
    }

    /**
     * Registra la solicitud ARCO+ para trazabilidad ante la autoridad.
     */
    private function registrar(
        Empleado $empleado,
        string $tipo,
        string $estado,
        ?string $motivo,
        ?string $resultado
    ): SolicitudArco {
        return SolicitudArco::create([
            'empresa_id'     => $empleado->empresa_id,
            'titular_type'   => Empleado::class,
            'titular_id'     => $empleado->id,
            'tipo'           => $tipo,
            'estado'         => $estado,
            'solicitado_por' => optional(auth()->user())->email ?? 'sistema',
            'motivo'         => $motivo,
            'resultado'      => $resultado,
        ]);
    }

    /**
     * Enmascara un número de cuenta dejando visibles sólo los últimos 4 dígitos.
     */
    private function enmascararCuenta(?string $numero): ?string
    {
        if (empty($numero)) {
            return null;
        }

        $ultimos = substr($numero, -4);

        return str_repeat('*', max(0, strlen($numero) - 4)) . $ultimos;
    }
}
