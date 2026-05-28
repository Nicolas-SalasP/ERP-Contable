<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;
use Exception;

class AnticipoProveedorService
{
    public function registrar(int $empresaId, array $datos): AnticipoProveedor
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->find($datos['proveedor_id']);

        if (!$proveedor) {
            throw new Exception("Proveedor no encontrado o no pertenece a tu empresa.", 404);
        }

        return AnticipoProveedor::create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $datos['proveedor_id'],
            'monto' => $datos['monto'],
            'monto_original' => $datos['monto'],
            'saldo_disponible' => $datos['monto'],
            'fecha_real' => $datos['fecha'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'estado' => 'DISPONIBLE',
        ]);
    }

    public function aplicarAFactura(int $empresaId, int $anticipoId, int $facturaId, float $montoAplicar): AnticipoProveedor
    {
        return DB::transaction(function () use ($empresaId, $anticipoId, $facturaId, $montoAplicar) {
            $anticipo = AnticipoProveedor::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($anticipoId);

            if (!$anticipo) {
                throw new Exception("Anticipo no encontrado.", 404);
            }

            $saldoActual = (float) $anticipo->getRawOriginal('saldo_disponible');
            if ($anticipo->getRawOriginal('saldo_disponible') === null) {
                $saldoActual = (float) $anticipo->monto;
                $anticipo->saldo_disponible = $saldoActual;
                $anticipo->monto_original = $anticipo->monto;
            }

            if ($anticipo->estado === 'APLICADO' || $saldoActual <= 0) {
                throw new Exception("El anticipo ya fue aplicado completamente.");
            }

            if ($montoAplicar > $saldoActual) {
                throw new Exception(
                    "Monto a aplicar ({$montoAplicar}) excede el saldo disponible ({$saldoActual})."
                );
            }

            $nuevoSaldo = round($saldoActual - $montoAplicar, 2);

            $anticipo->saldo_disponible = $nuevoSaldo;
            if ($nuevoSaldo <= 0.01) {
                $anticipo->estado = 'APLICADO';
                $anticipo->saldo_disponible = 0;
            }
            $anticipo->save();

            return $anticipo->fresh();
        });
    }

    public function listar(int $empresaId, ?int $proveedorId = null)
    {
        $query = AnticipoProveedor::where('empresa_id', $empresaId)
            ->with('proveedor');

        if ($proveedorId) {
            $query->where('proveedor_id', $proveedorId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
