<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Carbon;

/**
 * Servicio de Cuentas por Cobrar por Antigüedad (AR Aging).
 *
 * Calcula el saldo pendiente de cobro agrupado por cliente y
 * clasificado en tramos de vencimiento: corriente, 1-30, 31-60,
 * 61-90 y más de 90 días. El aislamiento multitenant lo garantiza
 * EmpresaScope sobre el modelo Factura.
 */
class ArAgingService
{
    /**
     * Genera el reporte de Cuentas por Cobrar por Antigüedad.
     *
     * Sólo incluye facturas de VENTA con estado pendiente de cobro
     * (excluye PAGADA y ANULADA). Agrupa por cliente (cliente_id)
     * y suma monto_bruto en el tramo correspondiente según días desde
     * el vencimiento.
     *
     * @return array{resumen: array<string, float>, detalle: list<array<string, mixed>>}
     */
    public function obtenerReporte(): array
    {
        $hoy = Carbon::today();

        // EmpresaScope filtra automáticamente por empresa_activa_id del usuario.
        $facturas = Factura::with('cliente')
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->get();

        /** @var array<int, array<string, mixed>> $porCliente */
        $porCliente = [];

        foreach ($facturas as $factura) {
            $clienteId   = (int) ($factura->cliente_id ?? 0);
            $razonSocial = (string) ($factura->cliente->razon_social ?? 'Sin nombre');
            $rut         = (string) ($factura->cliente->rut ?? '');
            $monto       = (float)  ($factura->monto_bruto ?? 0);

            // dias_vencido positivo = atrasado; negativo o null = corriente
            $vcto        = $factura->fecha_vencimiento;
            $diasVencido = ($vcto instanceof Carbon)
                ? (int) round($hoy->diffInDays($vcto, false) * -1)
                : null;

            if (!array_key_exists($clienteId, $porCliente)) {
                $porCliente[$clienteId] = [
                    'cliente_id'   => $clienteId,
                    'razon_social' => $razonSocial,
                    'rut'          => $rut,
                    'corriente'    => 0.0,
                    'd30'          => 0.0,
                    'd60'          => 0.0,
                    'd90'          => 0.0,
                    'd90plus'      => 0.0,
                    'total'        => 0.0,
                ];
            }

            $bucket = $this->clasificarBucket($diasVencido);

            /** @var float $valorActual */
            $valorActual = $porCliente[$clienteId][$bucket];
            $porCliente[$clienteId][$bucket] = $valorActual + $monto;

            /** @var float $totalActual */
            $totalActual = $porCliente[$clienteId]['total'];
            $porCliente[$clienteId]['total'] = $totalActual + $monto;
        }

        $detalle = array_values($porCliente);

        $resumen = [
            'corriente' => 0.0,
            'd30'       => 0.0,
            'd60'       => 0.0,
            'd90'       => 0.0,
            'd90plus'   => 0.0,
            'total'     => 0.0,
        ];

        foreach ($detalle as $fila) {
            $resumen['corriente'] += (float) $fila['corriente'];
            $resumen['d30']       += (float) $fila['d30'];
            $resumen['d60']       += (float) $fila['d60'];
            $resumen['d90']       += (float) $fila['d90'];
            $resumen['d90plus']   += (float) $fila['d90plus'];
            $resumen['total']     += (float) $fila['total'];
        }

        return [
            'resumen' => $resumen,
            'detalle' => $detalle,
        ];
    }

    /**
     * Clasifica los días desde el vencimiento en un tramo de aging.
     *
     * @param int|null $diasVencido Días desde el vencimiento (positivo = atrasado).
     * @return string corriente | d30 | d60 | d90 | d90plus
     */
    private function clasificarBucket(?int $diasVencido): string
    {
        if ($diasVencido === null || $diasVencido <= 0) {
            return 'corriente';
        }

        if ($diasVencido <= 30) {
            return 'd30';
        }

        if ($diasVencido <= 60) {
            return 'd60';
        }

        if ($diasVencido <= 90) {
            return 'd90';
        }

        return 'd90plus';
    }
}
