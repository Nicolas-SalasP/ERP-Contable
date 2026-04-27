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

    public function obtenerCatalogoBasico(int $empresaId)
    {
        return Proveedor::where('empresa_id', $empresaId)
            ->select('id', 'rut', 'razon_social', 'codigo_interno')
            ->orderBy('razon_social')
            ->get();
    }

    public function registrarProveedor(array $datos): Proveedor
    {
        $codigo = !empty($datos['codigo']) ? $datos['codigo'] : 'PROV-' . rand(1000, 9999);
        $codigoExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
            ->where('codigo_interno', $codigo)
            ->exists();

        if ($codigoExiste) {
            throw new Exception("El código interno {$codigo} ya está en uso.");
        }

        if (!empty($datos['rut'])) {
            $rutExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->where('rut', $datos['rut'])
                ->exists();

            if ($rutExiste) {
                throw new Exception("El proveedor con identificador {$datos['rut']} ya se encuentra registrado.");
            }
        }

        return Proveedor::create([
            'empresa_id' => $datos['empresa_id'],
            'codigo_interno' => $codigo,
            'rut' => $datos['rut'] ?? null,
            'razon_social' => $datos['razonSocial'],
            'pais_iso' => $datos['paisIso'] ?? 'CL',
            'moneda_defecto' => $datos['moneda'] ?? 'CLP',
            'nombre_contacto' => $datos['nombreContacto'] ?? null,
            'email_contacto' => $datos['emailContacto'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);
    }
}