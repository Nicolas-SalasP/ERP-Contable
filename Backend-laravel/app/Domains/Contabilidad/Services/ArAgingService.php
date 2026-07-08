<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Saldo pendiente de cobro por cliente, en tramos de vencimiento (corriente, 1-30, 31-60, 61-90, +90 días); aislamiento multitenant vía EmpresaScope sobre Factura. */
class ArAgingService
{
    /** Mismo TTL que DashboardResumenService: sin este cache, /ar-aging repite el trabajo ya cacheado por el dashboard. */
    private const TTL_SEGUNDOS = 90;

    /**
     * Incluye solo facturas de VENTA pendientes de cobro (excluye PAGADA y ANULADA), agrupadas por cliente.
     *
     * @return array{resumen: array<string, float>, detalle: list<array<string, mixed>>}
     */
    public function obtenerReporte(): array
    {
        $empresaId = auth()->check() ? auth()->user()->empresa_activa_id : null;

        if ($empresaId === null) {
            return $this->calcular();
        }

        return Cache::remember(
            "ar_aging:empresa_{$empresaId}",
            self::TTL_SEGUNDOS,
            fn () => $this->calcular()
        );
    }

    /**
     * @return array{resumen: array<string, float>, detalle: list<array<string, mixed>>}
     */
    private function calcular(): array
    {
        $hoy = Carbon::today();

        // notasCredito con estado APLICADA: mismo criterio de descuento de deuda que ProveedorService::compensarPartidas (equivalente en ventas).
        $facturas = Factura::with(['cliente', 'notasCredito' => function ($q) {
                $q->where('estado', 'APLICADA');
            }])
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            // La NC ya se descuenta como ajuste de su factura de origen (ver abajo); si no se excluye acá, se contaría dos veces.
            ->whereNotIn('tipo_documento', ['NOTA_CREDITO', 'NOTA_CREDITO_EXPORTACION'])
            ->get();

        /** @var array<int, array<string, mixed>> $porCliente */
        $porCliente = [];

        foreach ($facturas as $factura) {
            $clienteId   = (int) ($factura->cliente_id ?? 0);
            $razonSocial = (string) ($factura->cliente->razon_social ?? 'Sin nombre');
            $rut         = (string) ($factura->cliente->rut ?? '');

            // Saldo real pendiente: bruto menos NC aplicadas contra esta factura (evita sobreestimar facturas ABONADA por abono parcial).
            $montoNc = (float) $factura->notasCredito->sum('monto_bruto');
            $monto   = max(0.0, (float) ($factura->monto_bruto ?? 0) - $montoNc);

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
