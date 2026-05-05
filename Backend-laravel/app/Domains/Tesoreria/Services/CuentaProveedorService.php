<?php

namespace App\Domains\Tesoreria\Services;

use App\Domains\Tesoreria\Models\CuentaBancariaProveedor;
use App\Domains\Comercial\Models\Proveedor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class CuentaProveedorService
{
    public function obtenerPorProveedor(int $empresaId, int $proveedorId)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->where('id', $proveedorId)->first();

        if (!$proveedor) {
            throw new ModelNotFoundException("Proveedor no encontrado o acceso denegado.");
        }

        return CuentaBancariaProveedor::where('proveedor_id', $proveedorId)->get();
    }

    public function registrar(int $empresaId, array $datos)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)->where('id', $datos['proveedorId'])->first();

        if (!$proveedor) {
            throw new ModelNotFoundException("Proveedor no encontrado o acceso denegado.");
        }

        return CuentaBancariaProveedor::create([
            'proveedor_id' => $datos['proveedorId'],
            'banco' => $datos['banco'],
            'numero_cuenta' => $datos['numeroCuenta'],
            'tipo_cuenta' => $datos['tipoCuenta'],
            'pais_iso' => $datos['paisIso'] ?? 'CL'
        ]);
    }

    public function eliminar(int $empresaId, int $id)
    {
        $cuenta = CuentaBancariaProveedor::with('proveedor')->findOrFail($id);

        if (!$cuenta->proveedor || $cuenta->proveedor->empresa_id !== $empresaId) {
            throw new Exception("Acceso denegado a esta cuenta.", 403);
        }

        return $cuenta->delete();
    }
}