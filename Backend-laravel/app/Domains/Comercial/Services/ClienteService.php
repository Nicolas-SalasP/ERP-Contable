<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Cliente;
use Exception;

class ClienteService
{
    public function obtenerClientesPorEmpresa(int $empresaId)
    {
        return Cliente::where('empresa_id', $empresaId)
            ->orderBy('razon_social')
            ->get();
    }

    public function registrarCliente(array $datos): Cliente
    {
        $existe = Cliente::where('empresa_id', $datos['empresa_id'])
            ->where('rut', $datos['rut'])
            ->exists();

        if ($existe) {
            throw new Exception("El cliente con RUT {$datos['rut']} ya se encuentra registrado en su empresa.");
        }

        return Cliente::create($datos);
    }
}