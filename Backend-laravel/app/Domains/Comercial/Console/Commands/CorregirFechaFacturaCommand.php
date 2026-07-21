<?php

namespace App\Domains\Comercial\Console\Commands;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Contabilidad\Models\AsientoContable;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Corrige facturas de venta creadas via convertirEnFactura() con la fecha de HOY en
 * vez de la fecha real deseada -- el endpoint /cotizaciones/{id}/facturar aceptaba
 * fecha_emision pero el frontend nunca lo enviaba, asi que el default (hoy) se aplicaba
 * siempre. Corrige tanto factura.fecha_emision como la fecha del asiento contable
 * vinculado (comparten la misma fecha en convertirEnFactura, asi que ambos quedaron mal).
 */
class CorregirFechaFacturaCommand extends Command
{
    protected $signature = 'factura:corregir-fecha {factura_id} {fecha : Fecha real, formato YYYY-MM-DD}';

    protected $description = 'Corrige la fecha_emision de una factura de venta y la fecha de su asiento contable vinculado.';

    public function handle(): int
    {
        $facturaId = (int) $this->argument('factura_id');
        $fechaStr = $this->argument('fecha');

        try {
            $fecha = Carbon::createFromFormat('Y-m-d', $fechaStr)->startOfDay();
        } catch (\Exception) {
            $this->error("Fecha invalida: '{$fechaStr}'. Formato esperado: YYYY-MM-DD.");

            return self::FAILURE;
        }

        $factura = Factura::find($facturaId);
        if (! $factura) {
            $this->error("Factura {$facturaId} no existe.");

            return self::FAILURE;
        }

        $asiento = null;
        if ($factura->comprobante_contable) {
            $asiento = AsientoContable::where('empresa_id', $factura->empresa_id)
                ->where('numero_comprobante', $factura->comprobante_contable)
                ->first();
        }

        $this->info("Factura #{$factura->id} ({$factura->numero_factura}): {$factura->fecha_emision->format('Y-m-d')} -> {$fecha->format('Y-m-d')}");
        if ($asiento) {
            $this->info("Asiento vinculado #{$asiento->id} ({$asiento->numero_comprobante}): {$asiento->fecha->format('Y-m-d')} -> {$fecha->format('Y-m-d')}");
        } else {
            $this->warn('Esta factura no tiene asiento contable vinculado (comprobante_contable vacío); solo se corrige la factura.');
        }

        if (! $this->confirm('¿Confirmas la corrección?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($factura, $asiento, $fecha) {
            $factura->update(['fecha_emision' => $fecha]);
            $asiento?->update(['fecha' => $fecha]);
        });

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
