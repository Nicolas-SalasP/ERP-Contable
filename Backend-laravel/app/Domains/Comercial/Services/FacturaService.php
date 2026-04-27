<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;
use Exception;

class FacturaService
{
    public function obtenerFacturasPorEmpresa(int $empresaId, ?string $estado = null)
    {
        $query = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria']);

        if ($estado) {
            $query->where('estado', $estado);
        }

        return $query->orderBy('fecha_emision', 'desc')->get();
    }

    public function obtenerFacturasPaginadas(int $empresaId, array $filtros)
    {
        $query = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria']);

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['num'])) {
            $query->where('numero_factura', 'like', "%{$filtros['num']}%");
        }

        if (!empty($filtros['search'])) {
            $query->whereHas('proveedor', function ($q) use ($filtros) {
                $q->where('razon_social', 'like', "%{$filtros['search']}%")
                    ->orWhere('rut', 'like', "%{$filtros['search']}%");
            });
        }

        $limit = $filtros['limit'] ?? 10;
        return $query->orderBy('fecha_emision', 'desc')->paginate($limit);
    }

    public function obtenerFacturaPorId(int $empresaId, int $facturaId)
    {
        $factura = Factura::where('empresa_id', $empresaId)
            ->with(['proveedor', 'cuentaBancaria'])
            ->find($facturaId);

        if (!$factura) {
            throw new Exception("La factura solicitada no existe o no pertenece a su empresa.");
        }

        return $factura;
    }

    public function verificarDuplicado(int $empresaId, int $proveedorId, string $numero): bool
    {
        return Factura::where('empresa_id', $empresaId)
            ->where('proveedor_id', $proveedorId)
            ->where('numero_factura', $numero)
            ->exists();
    }

    public function registrarFacturaCompra(array $datos): Factura
    {
        if (!isset($datos['monto_neto']) || $datos['monto_neto'] <= 0) {
            throw new Exception("El monto neto debe ser mayor a 0.");
        }

        $neto = round((float) $datos['monto_neto'], 2);
        $iva = isset($datos['monto_iva']) ? round((float) $datos['monto_iva'], 2) : round($neto * 0.19, 2);
        $bruto = isset($datos['monto_bruto']) ? round((float) $datos['monto_bruto'], 2) : round($neto + $iva, 2);

        if (abs(($neto + $iva) - $bruto) > 0.01) {
            throw new Exception("Inconsistencia tributaria: El Neto + IVA no coincide con el Monto Bruto.");
        }

        return DB::transaction(function () use ($datos, $neto, $iva, $bruto) {
            $existe = $this->verificarDuplicado($datos['empresa_id'], $datos['proveedor_id'], $datos['numero_factura']);

            if ($existe) {
                throw new Exception("La factura {$datos['numero_factura']} ya se encuentra registrada para este proveedor.");
            }

            $codigoUnico = (int) (time() . rand(100, 999));

            return Factura::create([
                'empresa_id' => $datos['empresa_id'],
                'codigo_unico' => $codigoUnico,
                'proveedor_id' => $datos['proveedor_id'],
                'cuenta_bancaria_id' => $datos['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $datos['numero_factura'],
                'fecha_emision' => $datos['fecha_emision'],
                'fecha_vencimiento' => $datos['fecha_vencimiento'] ?? null,
                'monto_bruto' => $bruto,
                'monto_neto' => $neto,
                'monto_iva' => $iva,
                'estado' => 'REGISTRADA',
                'autorizador_id' => auth()->id() ?? $datos['autorizador_id'] ?? null,
            ]);
        });
    }
}