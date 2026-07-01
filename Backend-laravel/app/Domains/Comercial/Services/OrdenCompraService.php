<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Models\OrdenCompra;
use App\Domains\Comercial\Models\DetalleOrdenCompra;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrdenCompraService
{
    public function listar(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $query = OrdenCompra::with('proveedor')
            ->where('ordenes_compra.empresa_id', $empresaId)
            ->orderByDesc('fecha_emision');

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['proveedor'])) {
            $query->whereHas('proveedor', function ($q) use ($filtros) {
                $q->where('razon_social', 'like', '%' . $filtros['proveedor'] . '%');
            });
        }

        return $query->paginate(25);
    }

    public function crear(int $empresaId, array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($empresaId, $datos) {
            $numeroOc = $this->generarNumeroOc($empresaId);

            $detalles = $datos['detalles'] ?? [];
            unset($datos['detalles']);

            $subtotal = collect($detalles)->sum('subtotal');
            $impuesto = $datos['impuesto'] ?? round($subtotal * 0.19, 2);
            $total = $subtotal + $impuesto;

            /** @var OrdenCompra $oc */
            $oc = OrdenCompra::create(array_merge($datos, [
                'empresa_id' => $empresaId,
                'numero_oc' => $datos['numero_oc'] ?? $numeroOc,
                'subtotal' => $subtotal,
                'impuesto' => $impuesto,
                'total' => $total,
                'estado' => $datos['estado'] ?? 'BORRADOR',
            ]));

            foreach ($detalles as $detalle) {
                $detalle['orden_compra_id'] = $oc->id;
                DetalleOrdenCompra::create($detalle);
            }

            return $oc->load('detalles', 'proveedor');
        });
    }

    public function actualizar(OrdenCompra $oc, array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($oc, $datos) {
            $detalles = $datos['detalles'] ?? null;
            unset($datos['detalles']);

            if ($detalles !== null) {
                $oc->detalles()->delete();
                $subtotal = 0.0;
                foreach ($detalles as $detalle) {
                    $detalle['orden_compra_id'] = $oc->id;
                    DetalleOrdenCompra::create($detalle);
                    $subtotal += (float) ($detalle['subtotal'] ?? 0);
                }
                $impuesto = $datos['impuesto'] ?? round($subtotal * 0.19, 2);
                $datos['subtotal'] = $subtotal;
                $datos['impuesto'] = $impuesto;
                $datos['total'] = $subtotal + $impuesto;
            }

            $oc->update($datos);

            return $oc->load('detalles', 'proveedor');
        });
    }

    public function anular(OrdenCompra $oc): OrdenCompra
    {
        $oc->update(['estado' => 'ANULADA']);
        return $oc;
    }

    public function recibirParcial(OrdenCompra $oc, array $recepciones): OrdenCompra
    {
        DB::transaction(function () use ($oc, $recepciones) {
            foreach ($recepciones as $recepcion) {
                $detalleId = (int) ($recepcion['detalle_id'] ?? 0);
                $detalle = DetalleOrdenCompra::where('orden_compra_id', $oc->id)->where('id', $detalleId)->first();
                if ($detalle === null) {
                    continue;
                }
                $nuevaCantidad = (float) $detalle->cantidad_recibida + (float) ($recepcion['cantidad_recibida'] ?? 0);
                $detalle->update(['cantidad_recibida' => $nuevaCantidad]);
            }

            $oc->load('detalles');
            $todosRecibidos = $oc->detalles->every(
                fn ($d) => (float) $d->cantidad_recibida >= (float) $d->cantidad
            );
            $algunoRecibido = $oc->detalles->some(
                fn ($d) => (float) $d->cantidad_recibida > 0
            );

            if ($todosRecibidos) {
                $oc->update(['estado' => 'RECIBIDA_TOTAL']);
            } elseif ($algunoRecibido) {
                $oc->update(['estado' => 'RECIBIDA_PARCIAL']);
            }
        });

        return $oc->load('detalles', 'proveedor');
    }

    private function generarNumeroOc(int $empresaId): string
    {
        $anio = now()->year;
        $ultimo = OrdenCompra::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->whereYear('created_at', $anio)
            ->max(DB::raw("CAST(SUBSTR(numero_oc, -4) AS UNSIGNED)"));

        $siguiente = ($ultimo ?? 0) + 1;
        return sprintf('OC-%d-%04d', $anio, $siguiente);
    }
}
