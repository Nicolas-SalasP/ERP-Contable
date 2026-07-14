<?php

namespace App\Domains\Comercial\Console\Commands;

use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill para cotizaciones facturadas manualmente antes del fix de
 * CotizacionService::convertirEnFactura() (estado_id sin cast bloqueaba el flujo
 * normal). Vincula la factura ya existente en vez de dejar la cotizacion facturable
 * de nuevo -- sin esto, un segundo clic en "Convertir en factura" generaria un
 * duplicado real.
 */
class VincularCotizacionFacturaCommand extends Command
{
    protected $signature = 'cotizacion:vincular-factura
        {cotizacion_id : ID de la cotizacion ya aceptada/facturada a mano}
        {factura_id : ID de la factura de venta que ya existe para esa cotizacion}
        {--forzar : Omite el chequeo de que cliente_id coincida entre cotizacion y factura}';

    protected $description = 'Vincula una factura de venta ya existente a su cotizacion de origen y marca la cotizacion como Facturada.';

    public function handle(): int
    {
        $cotizacionId = (int) $this->argument('cotizacion_id');
        $facturaId = (int) $this->argument('factura_id');

        $cotizacion = Cotizacion::with('estado')->find($cotizacionId);
        if (! $cotizacion) {
            $this->error("Cotizacion {$cotizacionId} no existe.");

            return self::FAILURE;
        }

        $factura = Factura::find($facturaId);
        if (! $factura) {
            $this->error("Factura {$facturaId} no existe.");

            return self::FAILURE;
        }

        if ($factura->empresa_id !== $cotizacion->empresa_id) {
            $this->error("La factura {$facturaId} (empresa {$factura->empresa_id}) no pertenece a la misma empresa que la cotizacion {$cotizacionId} (empresa {$cotizacion->empresa_id}).");

            return self::FAILURE;
        }

        if ($factura->tipo !== 'VENTA') {
            $this->error("La factura {$facturaId} es tipo '{$factura->tipo}', no VENTA. Una cotizacion solo se vincula a una factura de venta.");

            return self::FAILURE;
        }

        if ($factura->cotizacion_id !== null) {
            $this->error("La factura {$facturaId} ya esta vinculada a la cotizacion {$factura->cotizacion_id}. Nada que hacer (o revisa si es un error).");

            return self::FAILURE;
        }

        if (! $this->option('forzar') && $factura->cliente_id !== $cotizacion->cliente_id) {
            $this->error("El cliente de la factura ({$factura->cliente_id}) no coincide con el de la cotizacion ({$cotizacion->cliente_id}). Si estas seguro que es el par correcto, repite con --forzar.");

            return self::FAILURE;
        }

        $this->info("Cotizacion #{$cotizacion->id} ({$cotizacion->numero_cotizacion}, estado actual: ".($cotizacion->estado->nombre ?? '?').') <-> Factura #'.$factura->id." ({$factura->numero_factura}, monto bruto: {$factura->monto_bruto})");

        if (! $this->confirm('¿Confirmas el vinculo?')) {
            $this->warn('Cancelado.');

            return self::SUCCESS;
        }

        $estadoFacturada = EstadoCotizacion::where('nombre', 'Facturada')->first();
        if (! $estadoFacturada) {
            $this->error("No existe el estado 'Facturada' en estado_cotizaciones. Nada se modifico.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($cotizacion, $factura, $estadoFacturada) {
            $factura->update(['cotizacion_id' => $cotizacion->id]);
            $cotizacion->update(['estado_id' => $estadoFacturada->id]);
        });

        $this->info('Listo. Cotizacion marcada como Facturada y factura vinculada.');

        return self::SUCCESS;
    }
}
