<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;

class CuentaProveedorService
{
    public function obtenerPorProveedor($proveedorId)
    {
        return CuentaBancariaProveedor::where('proveedor_id', $proveedorId)->get();
    }

    public function registrar(array $datos)
    {
        return CuentaBancariaProveedor::create([
            'proveedor_id'  => $datos['proveedorId'],
            'banco'         => $datos['banco'],
            'numero_cuenta' => $datos['numeroCuenta'],
            'tipo_cuenta'   => $datos['tipoCuenta'],
            'pais_iso'      => $datos['paisIso'] ?? 'CL'
        ]);
    }

    public function eliminar($id)
    {
        return CuentaBancariaProveedor::destroy($id);
    }
}