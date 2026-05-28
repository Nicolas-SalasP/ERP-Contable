<?php

namespace App\Domains\Sii\Console\Commands;

use App\Domains\Sii\Models\SiiEnvioDte;
use Illuminate\Console\Command;

/**
 * F5.3 — Lista envios SII en estado de error (ERROR_TRANSPORTE / ERROR_PERMANENTE
 * / ERROR_TIMEOUT / RECHAZADO) para revision e intervencion manual del
 * operador. Esta es la cola de "necesita atencion humana".
 */
class ListarEnviosFallidosCommand extends Command
{
    protected $signature = 'sii:listar-envios-fallidos
                            {--empresa= : Filtrar por ID de empresa}
                            {--dias=30 : Mostrar ultimos N dias (segun fecha_envio)}
                            {--ambiente= : certificacion|produccion}';

    protected $description = 'Lista envios al SII en estado de error o rechazo, con filtros y resumen por categoria.';

    public function handle(): int
    {
        $dias      = (int) $this->option('dias');
        $empresaId = $this->option('empresa');
        $ambiente  = $this->option('ambiente');

        $query = SiiEnvioDte::query()
            ->fallidos()
            ->where('created_at', '>=', now()->subDays($dias))
            ->with('dteEmitido')
            ->orderByDesc('created_at');

        if ($empresaId !== null && $empresaId !== '') {
            $query->where('empresa_id', (int) $empresaId);
        }
        if ($ambiente !== null && $ambiente !== '') {
            $query->where('ambiente_sii', $ambiente);
        }

        $envios = $query->get();

        if ($envios->isEmpty()) {
            $this->info('No hay envios fallidos en el periodo seleccionado.');
            return self::SUCCESS;
        }

        $filas = $envios->map(fn (SiiEnvioDte $e) => [
            $e->id,
            $e->dte_emitido_id,
            $e->dteEmitido?->folio ?? '-',
            $e->ambiente_sii,
            $e->estado_envio,
            $e->track_id ?? '-',
            $this->truncar((string) ($e->glosa_sii ?? ''), 40),
            $e->intentos_envio,
            $e->intentos_polling,
            $e->fecha_envio?->format('Y-m-d H:i') ?? '-',
            $e->fecha_resolucion?->format('Y-m-d H:i') ?? '-',
        ])->toArray();

        $this->table(
            ['envio', 'dte', 'folio', 'ambiente', 'estado', 'track', 'glosa', 'intEnv', 'intPoll', 'envio_at', 'resol_at'],
            $filas
        );

        $this->line('');
        $this->line('Total fallidos: ' . $envios->count());
        $porEstado = $envios->groupBy('estado_envio')->map(fn ($g) => $g->count());
        foreach ($porEstado as $estado => $cnt) {
            $this->line("  {$estado}: {$cnt}");
        }

        return self::SUCCESS;
    }

    private function truncar(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }
}
