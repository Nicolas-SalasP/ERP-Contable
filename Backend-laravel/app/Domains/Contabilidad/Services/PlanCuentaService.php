<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\PlanCuenta;
use Exception;

class PlanCuentaService
{
    public function listarCuentas(int $empresaId)
    {
        return PlanCuenta::where('empresa_id', $empresaId)
            ->where('activo', true)
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
}