<?php

namespace App\Domains\Contabilidad\Services;

use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use Illuminate\Support\Facades\DB;
use Exception;

class AsientoContableService
{
    public function registrarAsiento(array $datosAsiento, array $detalles): AsientoContable
    {
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($detalles as $detalle) {
            $totalDebe += round((float) ($detalle['debe'] ?? 0), 2);
            $totalHaber += round((float) ($detalle['haber'] ?? 0), 2);
        }
        if (abs($totalDebe - $totalHaber) > 0.01) {
            throw new Exception("Rechazado por Partida Doble: El Debe ({$totalDebe}) no cuadra con el Haber ({$totalHaber}).");
        }

        return DB::transaction(function () use ($datosAsiento, $detalles) {
            $asiento = AsientoContable::create($datosAsiento);
            foreach ($detalles as $detalle) {
                $asiento->detalles()->create([
                    'cuenta_contable' => $detalle['cuenta_contable'],
                    'fecha'           => $detalle['fecha'] ?? $asiento->fecha,
                    'tipo_operacion'  => $detalle['tipo_operacion'] ?? null,
                    'debe'            => $detalle['debe'] ?? 0.00,
                    'haber'           => $detalle['haber'] ?? 0.00,
                ]);
            }

            return $asiento;
        });
    }
}