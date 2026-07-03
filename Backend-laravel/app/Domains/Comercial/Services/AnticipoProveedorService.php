<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;

use App\Domains\Comercial\Models\AnticipoProveedor;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;

class AnticipoProveedorService
{
    public function registrar(int $empresaId, array $datos): AnticipoProveedor
    {
        $proveedor = Proveedor::where('empresa_id', $empresaId)
            ->find($datos['proveedor_id']);

        if (!$proveedor) {
            throw ComercialException::noEncontrado("Proveedor no encontrado o no pertenece a tu empresa.");
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
                throw ComercialException::noEncontrado("Anticipo no encontrado.");
            }

            $saldoActual = (float) $anticipo->getRawOriginal('saldo_disponible');
            if ($anticipo->getRawOriginal('saldo_disponible') === null) {
                $saldoActual = (float) $anticipo->monto;
                $anticipo->saldo_disponible = $saldoActual;
                $anticipo->monto_original = $anticipo->monto;
            }

            if ($anticipo->estado === 'APLICADO' || $saldoActual <= 0) {
                throw ComercialException::regla("El anticipo ya fue aplicado completamente.");
            }

            if ($montoAplicar > $saldoActual) {
                throw ComercialException::regla(
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

            // Registra a qué factura se aplicó este monto. Permite que anular esa
            // factura despues (FacturaService::anularFactura) libere el saldo.
            DB::table('anticipo_aplicaciones')->insert([
                'empresa_id' => $empresaId,
                'anticipo_id' => $anticipo->id,
                'factura_id' => $facturaId,
                'monto' => $montoAplicar,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $anticipo->fresh();
        });
    }

    /**
     * Revierte todas las aplicaciones de anticipo vigentes sobre una factura
     * anulada: repone el saldo disponible del anticipo y marca la aplicación
     * como revertida para no volver a liberarla dos veces.
     */
    public function revertirAplicacionesDeFactura(int $empresaId, int $facturaId): void
    {
        $aplicaciones = DB::table('anticipo_aplicaciones')
            ->where('empresa_id', $empresaId)
            ->where('factura_id', $facturaId)
            ->whereNull('revertido_at')
            ->get();

        foreach ($aplicaciones as $aplicacion) {
            $anticipo = AnticipoProveedor::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($aplicacion->anticipo_id);

            if ($anticipo) {
                $saldoActual = (float) ($anticipo->getRawOriginal('saldo_disponible') ?? $anticipo->monto);
                $anticipo->saldo_disponible = min(
                    (float) ($anticipo->monto_original ?? $anticipo->monto),
                    $saldoActual + (float) $aplicacion->monto
                );
                $anticipo->estado = 'DISPONIBLE';
                $anticipo->save();
            }

            DB::table('anticipo_aplicaciones')->where('id', $aplicacion->id)->update(['revertido_at' => now()]);
        }
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
