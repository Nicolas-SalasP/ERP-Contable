<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;

use App\Domains\Comercial\Models\AnticipoCliente;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Facades\DB;

/**
 * Mirror de AnticipoProveedorService para el lado venta. anticipo_aplicaciones
 * es una tabla compartida con proveedor: todas las escrituras/lecturas fijan
 * tipo='cliente' explicitamente para no colisionar con IDs de anticipos de
 * proveedor (ver hallazgo de auditoria #11).
 */
class AnticipoClienteService
{
    public function registrar(int $empresaId, array $datos): AnticipoCliente
    {
        $cliente = Cliente::where('empresa_id', $empresaId)
            ->find($datos['cliente_id']);

        if (!$cliente) {
            throw ComercialException::noEncontrado("Cliente no encontrado o no pertenece a tu empresa.");
        }

        return AnticipoCliente::create([
            'empresa_id' => $empresaId,
            'cliente_id' => $datos['cliente_id'],
            'monto' => $datos['monto'],
            'monto_original' => $datos['monto'],
            'saldo_disponible' => $datos['monto'],
            'fecha_real' => $datos['fecha'] ?? null,
            'referencia' => $datos['referencia'] ?? null,
            'estado' => 'DISPONIBLE',
        ]);
    }

    public function aplicarAFactura(int $empresaId, int $anticipoId, int $facturaId, float $montoAplicar): AnticipoCliente
    {
        return DB::transaction(function () use ($empresaId, $anticipoId, $facturaId, $montoAplicar) {
            $anticipo = AnticipoCliente::where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->find($anticipoId);

            if (!$anticipo) {
                throw ComercialException::noEncontrado("Anticipo no encontrado.");
            }

            $factura = Factura::where('empresa_id', $empresaId)->find($facturaId);
            if (!$factura) {
                throw ComercialException::noEncontrado("Factura no encontrada.");
            }
            if ($factura->tipo !== 'VENTA') {
                throw ComercialException::regla("Un anticipo de cliente solo puede aplicarse a una factura de venta.");
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

            // Registra la factura vinculada para que anularla despues (FacturaService::anularFactura) libere el saldo.
            DB::table('anticipo_aplicaciones')->insert([
                'empresa_id' => $empresaId,
                'anticipo_id' => $anticipo->id,
                'tipo' => 'cliente',
                'factura_id' => $facturaId,
                'monto' => $montoAplicar,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $anticipo->fresh();
        });
    }

    /** Marca cada aplicación como revertida (revertido_at) para no volver a liberar el mismo saldo dos veces. */
    public function revertirAplicacionesDeFactura(int $empresaId, int $facturaId): void
    {
        $aplicaciones = DB::table('anticipo_aplicaciones')
            ->where('empresa_id', $empresaId)
            ->where('factura_id', $facturaId)
            ->where('tipo', 'cliente')
            ->whereNull('revertido_at')
            ->get();

        foreach ($aplicaciones as $aplicacion) {
            $anticipo = AnticipoCliente::where('empresa_id', $empresaId)
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

            DB::table('anticipo_aplicaciones')
                ->where('id', $aplicacion->id)
                ->where('tipo', 'cliente')
                ->update(['revertido_at' => now()]);
        }
    }

    public function listar(int $empresaId, ?int $clienteId = null)
    {
        $query = AnticipoCliente::where('empresa_id', $empresaId)
            ->with('cliente');

        if ($clienteId) {
            $query->where('cliente_id', $clienteId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
