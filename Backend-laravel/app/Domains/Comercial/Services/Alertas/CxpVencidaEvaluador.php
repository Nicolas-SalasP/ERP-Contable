<?php

namespace App\Domains\Comercial\Services\Alertas;

use App\Domains\Alertas\Contracts\EvaluadorAlerta;
use App\Domains\Alertas\Models\Alerta;
use App\Domains\Alertas\Support\CandidatoAlerta;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Core\Scopes\EmpresaScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alerta por facturas de COMPRA vencidas con saldo pendiente de pago. Mismo criterio de saldo
 * real (bruto - NC aplicadas - anticipos consumidos) que ApAgingService.
 */
class CxpVencidaEvaluador implements EvaluadorAlerta
{
    private const DIAS_ADVERTENCIA = 30;

    private const DIAS_CRITICO = 60;

    public function tipo(): string
    {
        return 'cxp_vencida';
    }

    public function evaluar(): Collection
    {
        $hoy = Carbon::today();

        $facturas = Factura::withoutGlobalScope(EmpresaScope::class)
            ->with(['proveedor', 'notasCredito' => function ($q) {
                $q->where('estado', 'APLICADA');
            }])
            ->where('tipo', 'COMPRA')
            ->whereNotIn('estado', ['PAGADA', 'ANULADA'])
            ->whereNotIn('tipo_documento', ['NOTA_CREDITO', 'NOTA_CREDITO_EXPORTACION'])
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<', $hoy)
            ->get();

        $anticiposAplicados = DB::table('anticipo_aplicaciones')
            ->whereIn('factura_id', $facturas->pluck('id'))
            ->whereNull('revertido_at')
            ->groupBy('factura_id')
            ->selectRaw('factura_id, SUM(monto) as total')
            ->pluck('total', 'factura_id');

        $candidatos = collect();

        foreach ($facturas as $factura) {
            $montoNc = (float) $factura->notasCredito->sum('monto_bruto');
            $montoAnticipos = (float) ($anticiposAplicados[$factura->id] ?? 0);
            $saldo = max(0.0, (float) ($factura->monto_bruto ?? 0) - $montoNc - $montoAnticipos);

            if ($saldo <= 0.0) {
                continue;
            }

            $diasVencido = (int) $hoy->diffInDays($factura->fecha_vencimiento, false) * -1;
            $nivel = match (true) {
                $diasVencido > self::DIAS_CRITICO => Alerta::NIVEL_CRITICO,
                $diasVencido > self::DIAS_ADVERTENCIA => Alerta::NIVEL_ADVERTENCIA,
                default => Alerta::NIVEL_INFO,
            };

            $proveedor = (string) ($factura->proveedor->razon_social ?? 'proveedor sin nombre');

            $candidatos->push(new CandidatoAlerta(
                empresaId: (int) $factura->empresa_id,
                tipo: $this->tipo(),
                nivel: $nivel,
                entidadType: Factura::class,
                entidadId: (int) $factura->id,
                mensaje: "Factura de compra N° {$factura->numero_factura} de {$proveedor} vencida hace {$diasVencido} dias, saldo pendiente $".number_format($saldo, 0, ',', '.'),
                datos: [
                    'factura_id' => $factura->id,
                    'proveedor_id' => $factura->proveedor_id,
                    'saldo' => $saldo,
                    'dias_vencido' => $diasVencido,
                ],
                esDiaria: $nivel === Alerta::NIVEL_CRITICO,
            ));
        }

        return $candidatos;
    }
}
