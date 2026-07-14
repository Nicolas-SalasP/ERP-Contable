<?php

namespace App\Domains\Comercial\Console\Commands;

use App\Domains\Comercial\Models\Cotizacion;
use Illuminate\Console\Command;

/**
 * Diagnostico de solo lectura para el backfill post-hotfix v1.11.1: lista cotizaciones
 * en estado 'Aceptada'/'Facturada' que NO tienen ninguna Factura con cotizacion_id
 * apuntando a ellas -- candidatas a haberse facturado manualmente (o via DTE directo)
 * mientras el bug de estado_id bloqueaba (o mientras alguien forzo el estado a mano
 * como workaround). No modifica nada.
 */
class ReporteCotizacionesSinFacturaCommand extends Command
{
    protected $signature = 'cotizacion:reporte-sin-factura {--empresa=} {--formato=tabla : tabla|csv}';

    protected $description = 'Lista cotizaciones Aceptada/Facturada sin ninguna Factura vinculada (cotizacion_id). Solo lectura.';

    public function handle(): int
    {
        $query = Cotizacion::query()
            ->with(['estado', 'cliente'])
            ->whereHas('estado', fn ($q) => $q->whereIn('nombre', ['Aceptada', 'Facturada']))
            ->whereDoesntHave('facturas')
            ->orderBy('empresa_id')
            ->orderBy('fecha_emision');

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', (int) $empresaId);
        }

        $cotizaciones = $query->get();

        if ($cotizaciones->isEmpty()) {
            $this->info('No hay cotizaciones Aceptada/Facturada sin factura vinculada.');

            return self::SUCCESS;
        }

        $filas = $cotizaciones->map(fn (Cotizacion $c) => [
            'id' => $c->id,
            'empresa_id' => $c->empresa_id,
            'numero_cotizacion' => $c->numero_cotizacion,
            'cliente' => $c->cliente->razon_social ?? $c->nombre_cliente,
            'rut_cliente' => $c->cliente->rut ?? '?',
            'monto_total' => number_format((float) $c->monto_total, 0, ',', '.'),
            'estado' => $c->estado->nombre ?? '?',
            'fecha_emision' => $c->fecha_emision->format('Y-m-d'),
        ]);

        if ($this->option('formato') === 'csv') {
            $this->line('id;empresa_id;numero_cotizacion;cliente;rut_cliente;monto_total;estado;fecha_emision');
            foreach ($filas as $f) {
                $this->line(implode(';', $f));
            }
        } else {
            $this->table(
                ['ID', 'Empresa', 'N° Cotización', 'Cliente', 'RUT', 'Monto Total', 'Estado', 'Fecha'],
                $filas->toArray()
            );
        }

        $this->newLine();
        $this->info("Total: {$cotizaciones->count()} cotizaciones sin factura vinculada.");

        return self::SUCCESS;
    }
}
