<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Proveedor;
use Exception;

class ProveedorService
{
    public function obtenerProveedoresPorEmpresa(int $empresaId)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->with('cuentasBancarias')
            ->orderBy('razon_social')
            ->get();
    }

    public function registrarProveedor(array $datos): Proveedor
    {
        $codigoExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
            ->where('codigo_interno', $datos['codigo_interno'])
            ->exists();

        if ($codigoExiste) {
            throw new Exception("El código interno {$datos['codigo_interno']} ya está en uso.");
        }

        if (!empty($datos['rut'])) {
            $rutExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->where('rut', $datos['rut'])
                ->exists();

            if ($rutExiste) {
                throw new Exception("El proveedor con RUT {$datos['rut']} ya se encuentra registrado.");
            }
        }

        return Proveedor::create($datos);
    }
}