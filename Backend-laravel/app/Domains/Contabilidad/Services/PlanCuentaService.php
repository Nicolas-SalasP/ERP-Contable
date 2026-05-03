<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use Illuminate\Support\Facades\DB;
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

    public function actualizarCuenta($empresa_id, $id, array $datos)
    {
        $cuenta = PlanCuenta::where('empresa_id', $empresa_id)->where('id', $id)->first();
        
        if (!$cuenta) {
            throw new Exception("La cuenta contable no existe o pertenece a otra empresa.");
        }

        if (isset($datos['codigo']) && $datos['codigo'] !== $cuenta->codigo) {
            $existe = PlanCuenta::where('empresa_id', $empresa_id)
                ->where('codigo', $datos['codigo'])
                ->exists();

            if ($existe) {
                throw new Exception("El código contable ya está en uso por otra cuenta.");
            }
        }

        $tieneMovimientos = DetalleAsiento::where('cuenta_contable', $cuenta->codigo)->exists();

        if ($tieneMovimientos) {
            if (isset($datos['tipo']) && $datos['tipo'] !== $cuenta->tipo) {
                throw new Exception("No puedes cambiar el tipo (naturaleza) de una cuenta que ya posee movimientos.");
            }
            if (isset($datos['activo']) && $datos['activo'] == false) {
                throw new Exception("No puedes inactivar una cuenta que ya posee movimientos históricos.");
            }
        }

        return DB::transaction(function () use ($cuenta, $datos) {
            $cuenta->update($datos);
            return $cuenta;
        });
    }
}