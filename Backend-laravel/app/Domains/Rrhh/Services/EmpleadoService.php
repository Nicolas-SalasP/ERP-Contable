<?php

namespace App\Domains\Rrhh\Services;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Empleado;
use Illuminate\Support\Facades\DB;

class EmpleadoService
{
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
            $query->where(function ($q) use ($filtros) {
                $q->where('nombres', 'like', "%{$filtros['buscar']}%")
                    ->orWhere('apellido_paterno', 'like', "%{$filtros['buscar']}%")
                    ->orWhere('apellido_materno', 'like', "%{$filtros['buscar']}%")
                    ->orWhere('rut', 'like', "%{$filtros['buscar']}%");
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
            $existe = Empleado::where('empresa_id', $empresaId)
                ->where('rut', $datos['rut'])
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

            return $empleado->fresh();
        });
    }

    public function actualizar(int $empresaId, int $id, array $datos): Empleado
    {
        return DB::transaction(function () use ($empresaId, $id, $datos) {
            $empleado = $this->obtener($empresaId, $id);

            if (isset($datos['rut']) && $datos['rut'] !== $empleado->rut) {
                $existe = Empleado::where('empresa_id', $empresaId)
                    ->where('rut', $datos['rut'])
                    ->where('id', '!=', $id)
                    ->exists();
                if ($existe) {
                    throw RrhhException::regla("El RUT {$datos['rut']} ya está registrado en otro empleado.");
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
