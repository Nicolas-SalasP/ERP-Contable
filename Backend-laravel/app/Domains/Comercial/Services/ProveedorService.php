<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
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
        if (!empty($datos['rut'])) {
            $rutExiste = Proveedor::where('empresa_id', $datos['empresa_id'])
                ->where('rut', $datos['rut'])
                ->exists();

            if ($rutExiste) {
                throw new Exception("El proveedor con identificador {$datos['rut']} ya se encuentra registrado.");
            }
        }

        $proveedor = Proveedor::create([
            'empresa_id' => $datos['empresa_id'],
            'codigo_interno' => 'TEMP',
            'rut' => $datos['rut'] ?? null,
            'razon_social' => $datos['razonSocial'] ?? $datos['razon_social'],
            'pais_iso' => $datos['paisIso'] ?? 'CL',
            'moneda_defecto' => $datos['moneda'] ?? 'CLP',
            'nombre_contacto' => $datos['nombreContacto'] ?? null,
            'email_contacto' => $datos['emailContacto'] ?? null,
            'direccion' => $datos['direccion'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
        ]);

        $proveedor->update([
            'codigo_interno' => 'PROV-' . str_pad($proveedor->id, 5, '0', STR_PAD_LEFT)
        ]);

        return $proveedor;
    }

    public function obtenerFichaProveedor(int $empresaId, int $id)
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->with(['cuentasBancarias', 'pais'])
            ->find($id);

        if (!$proveedor) {
            throw new Exception("El proveedor solicitado no existe.");
        }

        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('proveedor_id', $id)
            ->orderBy('fecha_emision', 'desc')
            ->get();

        $anticipos = [];
        return [
            'proveedor' => $proveedor,
            'facturas'  => $facturas,
            'anticipos' => $anticipos
        ];
    }
}