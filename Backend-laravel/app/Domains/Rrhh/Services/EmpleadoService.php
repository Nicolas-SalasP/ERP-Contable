<?php

namespace App\Domains\Rrhh\Services;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Sii\Support\RutHelper;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmpleadoService
{
    /**
     * Normaliza un RUT para almacenamiento y consulta de blind index.
     * Devuelve null si la cadena no es un RUT parseable (p. ej. búsquedas por nombre).
     */
    private function normalizarRut(string $valor): ?string
    {
        try {
            return RutHelper::normalizar($valor);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public function listar(int $empresaId, array $filtros = [])
    {
        $query = Empleado::where('empresa_id', $empresaId)
            ->with(['contratoActivo'])
            ->orderBy('apellido_paterno')
            ->orderBy('nombres');

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (!empty($filtros['buscar'])) {
            $buscar = $filtros['buscar'];
            $rutNormalizado = $this->normalizarRut($buscar);

            $query->where(function ($q) use ($buscar, $rutNormalizado) {
                $q->where('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellido_paterno', 'like', "%{$buscar}%")
                    ->orWhere('apellido_materno', 'like', "%{$buscar}%");

                // Búsqueda por RUT: sólo si el término es un RUT parseable (búsqueda exacta
                // vía blind index). La búsqueda parcial ya no es posible con RUT cifrado.
                if ($rutNormalizado !== null) {
                    $q->orWhereBlind('rut', 'empleado_rut_index', $rutNormalizado);
                }
            });
        }

        return $query->paginate($filtros['por_pagina'] ?? 20);
    }

    public function obtener(int $empresaId, int $id): Empleado
    {
        $empleado = Empleado::where('empresa_id', $empresaId)
            ->with(['contratos', 'cargasFamiliares'])
            ->find($id);

        if (!$empleado) {
            throw RrhhException::noEncontrado('El empleado no existe o no pertenece a la empresa.');
        }

        return $empleado;
    }

    public function crear(int $empresaId, array $datos): Empleado
    {
        return DB::transaction(function () use ($empresaId, $datos) {
            // Normalizar el RUT antes de almacenar y consultar el blind index
            if (isset($datos['rut'])) {
                $datos['rut'] = RutHelper::normalizar($datos['rut']);
            }

            $existe = Empleado::where('empresa_id', $empresaId)
                ->whereBlind('rut', 'empleado_rut_index', $datos['rut'])
                ->exists();

            if ($existe) {
                throw RrhhException::regla("Ya existe un empleado con el RUT {$datos['rut']} en esta empresa.");
            }

            $datos['empresa_id'] = $empresaId;

            $numeroCuenta = $datos['banco_numero_cuenta'] ?? null;
            unset($datos['banco_numero_cuenta']);

            $empleado = Empleado::create($datos);

            if ($numeroCuenta !== null) {
                $empleado->banco_numero_cuenta_cifrado = $numeroCuenta;
                $empleado->save();
            }

            // Registro de base jurídica para tratamiento de datos laborales (Ley 21.719 — Fase 4)
            // La base de licitud es ejecución del contrato/obligación legal, NO consentimiento.
            // Envuelto defensivamente para que un fallo aquí nunca bloquee la creación del empleado.
            try {
                $politicaVersion = \App\Domains\Core\Models\PoliticaPrivacidad::where('activa', true)
                    ->value('version');
                \App\Domains\Core\Models\Consentimiento::create([
                    'empresa_id'       => $empresaId,
                    'titular_type'     => \App\Domains\Rrhh\Models\Empleado::class,
                    'titular_id'       => $empleado->id,
                    'politica_version' => $politicaVersion,
                    'finalidad'        => 'gestion_laboral_y_remuneraciones',
                    'base_licitud'     => 'ejecucion_contrato',
                    'otorgado'         => true,
                    'otorgado_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                // Fallo defensivo: loguear pero no abortar la transacción
                \Illuminate\Support\Facades\Log::warning('No se pudo registrar consentimiento laboral para empleado', [
                    'empleado_id' => $empleado->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $empleado->fresh();
        });
    }

    public function actualizar(int $empresaId, int $id, array $datos): Empleado
    {
        return DB::transaction(function () use ($empresaId, $id, $datos) {
            $empleado = $this->obtener($empresaId, $id);

            if (isset($datos['rut'])) {
                // Normalizar el RUT entrante para comparación y almacenamiento
                $datos['rut'] = RutHelper::normalizar($datos['rut']);

                if ($datos['rut'] !== $empleado->rut) {
                    $existe = Empleado::where('empresa_id', $empresaId)
                        ->whereBlind('rut', 'empleado_rut_index', $datos['rut'])
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($existe) {
                        throw RrhhException::regla("El RUT {$datos['rut']} ya está registrado en otro empleado.");
                    }
                }
            }

            $numeroCuenta = $datos['banco_numero_cuenta'] ?? null;
            unset($datos['banco_numero_cuenta'], $datos['empresa_id']);

            $empleado->fill($datos);

            if ($numeroCuenta !== null) {
                $empleado->banco_numero_cuenta_cifrado = $numeroCuenta;
            }

            $empleado->save();

            return $empleado->fresh();
        });
    }

    public function eliminar(int $empresaId, int $id): void
    {
        $empleado = $this->obtener($empresaId, $id);

        $tieneContratosVigentes = $empleado->contratos()
            ->where('estado', 'VIGENTE')
            ->exists();

        if ($tieneContratosVigentes) {
            throw RrhhException::regla('No se puede eliminar un empleado con contratos vigentes. Primero debe terminar el contrato.');
        }

        $empleado->delete();
    }
}
