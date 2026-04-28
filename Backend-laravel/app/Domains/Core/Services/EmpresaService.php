<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Empresa;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Contabilidad\Models\CentroCosto;
use Illuminate\Support\Facades\Storage;
use Exception;

class EmpresaService
{
    public function actualizarDatos(int $empresaId, array $datos)
    {
        $empresa = Empresa::find($empresaId);
        if (!$empresa)
            throw new Exception("Empresa no encontrada.");

        $empresa->update($datos);
        return $empresa;
    }

    public function actualizarLogo(int $empresaId, $archivoLogo)
    {
        $empresa = Empresa::find($empresaId);
        if (!$empresa)
            throw new Exception("Empresa no encontrada.");

        if ($empresa->logo_path && Storage::disk('public')->exists($empresa->logo_path)) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $path = $archivoLogo->store('empresas/logos', 'public');

        $empresa->update(['logo_path' => $path]);

        return $path;
    }

    // --- BANCOS ---

    public function agregarBanco(int $empresaId, array $datos)
    {
        return CuentaBancariaEmpresa::create([
            'empresa_id' => $empresaId,
            'banco' => $datos['banco'],
            'tipo_cuenta' => $datos['tipo_cuenta'],
            'numero_cuenta' => $datos['numero_cuenta'],
            'titular' => $datos['titular'] ?? null,
            'rut_titular' => $datos['rut_titular'] ?? null,
            'email_notificacion' => $datos['email_notificacion'] ?? null,
            'moneda' => 'CLP',
            'activa' => true
        ]);
    }

    public function eliminarBanco(int $empresaId, int $cuentaId)
    {
        $cuenta = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->find($cuentaId);
        if (!$cuenta)
            throw new Exception("La cuenta bancaria no existe.");

        $cuenta->delete();
        return true;
    }

    public function actualizarBanco(int $empresaId, int $cuentaId, array $datos)
    {
        $cuenta = CuentaBancariaEmpresa::where('empresa_id', $empresaId)->find($cuentaId);
        
        if (!$cuenta) {
            throw new Exception("La cuenta bancaria no existe o no pertenece a tu empresa.");
        }

        $cuenta->update([
            'banco' => $datos['banco'] ?? $cuenta->banco,
            'tipo_cuenta' => $datos['tipo_cuenta'] ?? $cuenta->tipo_cuenta,
            'numero_cuenta' => $datos['numero_cuenta'] ?? $cuenta->numero_cuenta,
        ]);

        return $cuenta;
    }

    // --- CENTROS DE COSTO ---

    public function agregarCentroCosto(int $empresaId, array $datos)
    {
        $existe = CentroCosto::where('empresa_id', $empresaId)->where('codigo', $datos['codigo'])->exists();
        if ($existe)
            throw new Exception("El código '{$datos['codigo']}' ya está en uso.");

        return CentroCosto::create([
            'empresa_id' => $empresaId,
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre'],
            'activo' => true
        ]);
    }

    public function eliminarCentroCosto(int $empresaId, int $centroId)
    {
        $centro = CentroCosto::where('empresa_id', $empresaId)->find($centroId);
        if (!$centro)
            throw new Exception("El centro de costo no existe.");

        $centro->delete();
        return true;
    }

    public function actualizarCentroCosto(int $empresaId, int $centroId, array $datos)
    {
        $centro = CentroCosto::where('empresa_id', $empresaId)->find($centroId);
        if (!$centro) throw new Exception("El centro de costo no existe.");
        $existe = CentroCosto::where('empresa_id', $empresaId)
                             ->where('codigo', $datos['codigo'])
                             ->where('id', '!=', $centroId)
                             ->exists();
        if ($existe) throw new Exception("El código '{$datos['codigo']}' ya está en uso por otro centro.");

        $centro->update([
            'codigo' => $datos['codigo'],
            'nombre' => $datos['nombre']
        ]);

        return $centro;
    }
}