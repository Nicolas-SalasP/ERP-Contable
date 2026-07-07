<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Comercial\Models\Factura;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Saldo pendiente de pago por proveedor, en tramos de vencimiento (corriente, 1-30, 31-60, 61-90, +90 días); aislamiento multitenant vía EmpresaScope sobre Factura. */
class ApAgingService
{
    /** Mismo TTL que DashboardResumenService: sin este cache, /ap-aging repite el trabajo ya cacheado por el dashboard. */
    private const TTL_SEGUNDOS = 90;

    /**
     * Incluye solo facturas de COMPRA pendientes de pago (excluye PAGADA y ANULADA), agrupadas por proveedor.
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
            "ap_aging:empresa_{$empresaId}",
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

        $facturas = Factura::with('proveedor')
            ->where('tipo', 'COMPRA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->get();

        /** @var array<int, array<string, mixed>> $porProveedor */
        $porProveedor = [];

        foreach ($facturas as $factura) {
            $proveedorId = (int) ($factura->proveedor_id ?? 0);
            $razonSocial = (string) ($factura->proveedor->razon_social ?? 'Sin nombre');
            $rut         = (string) ($factura->proveedor->rut ?? '');
            $monto       = (float)  ($factura->monto_bruto ?? 0);

            // dias_vencido positivo = atrasado; negativo o null = corriente
            $vcto        = $factura->fecha_vencimiento;
            $diasVencido = ($vcto instanceof Carbon)
                ? (int) round($hoy->diffInDays($vcto, false) * -1)
                : null;

            if (!array_key_exists($proveedorId, $porProveedor)) {
                $porProveedor[$proveedorId] = [
                    'proveedor_id' => $proveedorId,
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
            $valorActual = $porProveedor[$proveedorId][$bucket];
            $porProveedor[$proveedorId][$bucket] = $valorActual + $monto;

            /** @var float $totalActual */
            $totalActual = $porProveedor[$proveedorId]['total'];
            $porProveedor[$proveedorId]['total'] = $totalActual + $monto;
        }

        $detalle = array_values($porProveedor);

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
