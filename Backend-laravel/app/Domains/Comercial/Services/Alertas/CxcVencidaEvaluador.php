<?php

namespace App\Domains\Comercial\Services\Alertas;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Scopes\EmpresaScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Alerta por facturas de VENTA vencidas con saldo pendiente de cobro. Mismo criterio de saldo
 * real (bruto - NC aplicadas) que ArAgingService, para no duplicar logica de negocio distinta.
 */
class CxcVencidaEvaluador implements EvaluadorAlerta
{
    private const DIAS_ADVERTENCIA = 30;

    private const DIAS_CRITICO = 60;

    public function tipo(): string
    {
        return 'cxc_vencida';
    }

    public function evaluar(): Collection
    {
        $hoy = Carbon::today();

        $facturas = Factura::withoutGlobalScope(EmpresaScope::class)
            ->with(['cliente', 'notasCredito' => function ($q) {
                $q->where('estado', 'APLICADA');
            }])
            ->where('tipo', 'VENTA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotIn('tipo_documento', ['NOTA_CREDITO', 'NOTA_CREDITO_EXPORTACION'])
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->get();

        $candidatos = collect();

        foreach ($facturas as $factura) {
            $montoNc = (float) $factura->notasCredito->sum('monto_bruto');
            $saldo = max(0.0, (float) ($factura->monto_bruto ?? 0) - $montoNc);

            if ($saldo <= 0.0) {
                continue;
            }

            $diasVencido = (int) $hoy->diffInDays($factura->fecha_vencimiento, false) * -1;
            $nivel = match (true) {
                $diasVencido > self::DIAS_CRITICO => Alerta::NIVEL_CRITICO,
                $diasVencido > self::DIAS_ADVERTENCIA => Alerta::NIVEL_ADVERTENCIA,
                default => Alerta::NIVEL_INFO,
            };

            $cliente = (string) ($factura->cliente->razon_social ?? 'cliente sin nombre');

            $candidatos->push(new CandidatoAlerta(
                empresaId: (int) $factura->empresa_id,
                tipo: $this->tipo(),
                nivel: $nivel,
                entidadType: Factura::class,
                entidadId: (int) $factura->id,
                mensaje: "Factura de venta N° {$factura->numero_factura} a {$cliente} vencida hace {$diasVencido} dias, saldo pendiente $".number_format($saldo, 0, ',', '.'),
                datos: [
                    'factura_id' => $factura->id,
                    'cliente_id' => $factura->cliente_id,
                    'saldo' => $saldo,
                    'dias_vencido' => $diasVencido,
                ],
                esDiaria: $nivel === Alerta::NIVEL_CRITICO,
            ));
        }

        return $candidatos;
    }
}
