<?php

namespace App\Domains\Comercial\Services;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\DetalleOrdenCompra;
use App\Domains\Comercial\Models\OrdenCompra;
use App\Domains\Core\Services\ContadorEmpresaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrdenCompraService
{
    public function __construct(
        private readonly ContadorEmpresaService $contadorService
    ) {}

    public function listar(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $query = OrdenCompra::with('proveedor')
            ->where('ordenes_compra.empresa_id', $empresaId)
            ->orderByDesc('fecha_emision');

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['proveedor'])) {
            $query->whereHas('proveedor', function ($q) use ($filtros) {
                $q->where('razon_social', 'like', '%'.$filtros['proveedor'].'%');
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
            $impuesto = $datos['impuesto'] ?? round($subtotal * config('fiscal.tasa_iva'), 2);
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
                $impuesto = $datos['impuesto'] ?? round($subtotal * config('fiscal.tasa_iva'), 2);
                $datos['subtotal'] = $subtotal;
                $datos['impuesto'] = $impuesto;
                $datos['total'] = $subtotal + $impuesto;
            }

            $oc->update($datos);

            return $oc->load('detalles', 'proveedor');
        });
    }

    private const ESTADOS_NO_ANULABLES = ['RECIBIDA_TOTAL', 'RECIBIDA_PARCIAL', 'ANULADA'];

    public function anular(OrdenCompra $oc): OrdenCompra
    {
        return DB::transaction(function () use ($oc) {
            /** @var OrdenCompra $ocLock */
            $ocLock = OrdenCompra::where('empresa_id', $oc->empresa_id)->lockForUpdate()->findOrFail($oc->id);

            if (in_array($ocLock->estado, self::ESTADOS_NO_ANULABLES, true)) {
                throw ComercialException::regla(
                    "No se puede anular una orden de compra en estado {$ocLock->estado}."
                );
            }

            $ocLock->update(['estado' => 'ANULADA']);

            return $ocLock;
        });
    }

    public function recibirParcial(OrdenCompra $oc, array $recepciones): OrdenCompra
    {
        return DB::transaction(function () use ($oc, $recepciones) {
            /** @var OrdenCompra $ocLock */
            $ocLock = OrdenCompra::where('empresa_id', $oc->empresa_id)->lockForUpdate()->findOrFail($oc->id);

            if ($ocLock->estado === 'ANULADA') {
                throw ComercialException::regla('No se puede recibir mercadería de una orden de compra anulada.');
            }

            foreach ($recepciones as $recepcion) {
                $detalleId = (int) ($recepcion['detalle_id'] ?? 0);
                $detalle = DetalleOrdenCompra::where('orden_compra_id', $ocLock->id)->where('id', $detalleId)->lockForUpdate()->first();
                if ($detalle === null) {
                    continue;
                }
                $nuevaCantidad = (float) $detalle->cantidad_recibida + (float) ($recepcion['cantidad_recibida'] ?? 0);
                $detalle->update(['cantidad_recibida' => $nuevaCantidad]);
            }

            $ocLock->load('detalles');
            $todosRecibidos = $ocLock->detalles->every(
                fn ($d) => (float) $d->cantidad_recibida >= (float) $d->cantidad
            );
            $algunoRecibido = $ocLock->detalles->some(
                fn ($d) => (float) $d->cantidad_recibida > 0
            );

            if ($todosRecibidos) {
                $ocLock->update(['estado' => 'RECIBIDA_TOTAL']);
            } elseif ($algunoRecibido) {
                $ocLock->update(['estado' => 'RECIBIDA_PARCIAL']);
            }

            return $ocLock->load('detalles', 'proveedor');
        });
    }

    private function generarNumeroOc(int $empresaId): string
    {
        $anio = now()->year;

        // El correlativo se codifica en el "tipo" del contador incluyendo el
        // anio (ej. 'orden_compra_2026') porque ContadorEmpresaService lleva
        // un contador monotono sin dimension de tiempo, y el formato de OC
        // reinicia el correlativo cada anio (OC-{anio}-{0001}).
        //
        // siguienteNumero() abre su propia DB::transaction, pero como ya
        // estamos dentro de la transaccion de crear(), Laravel la anida via
        // savepoint: si crear() hace rollback despues de haber consumido un
        // correlativo, el contador queda incrementado igual (queda un hueco
        // en la numeracion). Es una decision deliberada: un hueco es
        // preferible a arriesgar una colision contra el unique constraint
        // (empresa_id, numero_oc).
        $correlativo = $this->contadorService->siguienteNumero($empresaId, "orden_compra_{$anio}");

        return sprintf('OC-%d-%04d', $anio, $correlativo);
    }
}
