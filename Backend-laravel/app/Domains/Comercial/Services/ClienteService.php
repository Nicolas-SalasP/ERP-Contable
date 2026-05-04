<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Exception;

class ClienteService
{
    public function buscarClientesPorEmpresa(int $empresaId, ?string $search = null)
    {
        $query = Cliente::where('empresa_id', $empresaId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('rut', 'like', "%{$search}%")
                    ->orWhere('razon_social', 'like', "%{$search}%")
                    ->orWhere('codigo_cliente', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('razon_social')->get();
    }

    public function registrarCliente(array $datos): Cliente
    {
        $existe = Cliente::where('empresa_id', $datos['empresa_id'])
            ->where('rut', $datos['rut'])
            ->exists();

        if ($existe) {
            throw new Exception("El cliente con RUT {$datos['rut']} ya se encuentra registrado.");
        }

        return Cliente::create($datos);
    }

    public function inactivarCliente(int $empresaId, int $id)
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);
        $cliente->update(['estado' => 'INACTIVO']);
        return $cliente;
    }
    public function actualizarCliente($id, array $datos)
    {
        $cliente = Cliente::findOrFail($id); //

        if (isset($datos['rut']) && $datos['rut'] !== $cliente->rut) {
            $existe = Cliente::where('empresa_id', $cliente->empresa_id)
                ->where('rut', $datos['rut'])
                ->exists(); //

            if ($existe) {
                throw new Exception("El RUT ingresado ya está registrado para otro cliente en esta empresa."); //
            }
        }

        return DB::transaction(function () use ($cliente, $datos) {
            $cliente->update($datos); //
            return $cliente;
        });
    }
    
    public function activarCliente(int $empresaId, int $id): Cliente
    {
        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);
        $cliente->update(['estado' => 'ACTIVO']);
        return $cliente;
    }
}