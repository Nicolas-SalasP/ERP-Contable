<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\PlanCuenta;
use Exception;

class PlanCuentaService
{
    public function listarCuentas(int $empresaId)
    {
        return PlanCuenta::where('empresa_id', $empresaId)
            ->orderBy('codigo')
            ->get();
    }

    public function listarCuentasImputables(int $empresaId)
    {
        return PlanCuenta::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('imputable', true)
            ->orderBy('codigo')
            ->get();
    }

    public function registrarCuenta(array $datos): PlanCuenta
    {
        $existe = PlanCuenta::where('empresa_id', $datos['empresa_id'])
            ->where('codigo', $datos['codigo'])
            ->exists();

        if ($existe) {
            throw new Exception("El código de cuenta {$datos['codigo']} ya existe para su empresa.");
        }

        return PlanCuenta::create($datos);
    }

    public function actualizarCuenta(int $empresaId, int $id, array $datos): PlanCuenta
    {
        $cuenta = PlanCuenta::where('empresa_id', $empresaId)->find($id);

        if (!$cuenta) {
            throw new Exception("La cuenta contable no existe.");
        }

        if (isset($datos['codigo']) && $datos['codigo'] !== $cuenta->codigo) {
            $existe = PlanCuenta::where('empresa_id', $empresaId)
                ->where('codigo', $datos['codigo'])
                ->where('id', '!=', $id)
                ->exists();

            if ($existe) {
                throw new Exception("El código de cuenta {$datos['codigo']} ya está en uso por otra cuenta.");
            }
        }

        $cuenta->update($datos);
        return $cuenta;
    }
}