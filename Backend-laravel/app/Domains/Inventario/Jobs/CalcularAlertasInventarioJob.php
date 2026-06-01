<?php

namespace App\Domains\Inventario\Jobs;

use App\Domains\Inventario\Models\Producto;
use App\Domains\Inventario\Services\InventarioAlertaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalcularAlertasInventarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly ?int $empresaId = null
    ) {
        $this->onQueue('inventario');
    }

    public function handle(InventarioAlertaService $alertaService): void
    {
        $empresaIds = $this->empresaId !== null
            ? collect([$this->empresaId])
            : Producto::query()
                ->select('empresa_id')
                ->whereNotNull('empresa_id')
                ->distinct()
                ->pluck('empresa_id');

        foreach ($empresaIds as $empresaId) {
            $resultado = $alertaService->recalcularPersistidasParaEmpresa((int) $empresaId);

            Log::info('Alertas de Inventario recalculadas.', [
                'empresa_id' => (int) $empresaId,
                'total' => $resultado['resumen']['total'] ?? null,
                'criticas' => $resultado['resumen']['criticas'] ?? null,
            ]);
        }
    }
}