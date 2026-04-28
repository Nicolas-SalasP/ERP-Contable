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
                    'fecha' => $detalle['fecha'] ?? $asiento->fecha,
                    'tipo_operacion' => $detalle['tipo_operacion'] ?? null,
                    'debe' => $detalle['debe'] ?? 0.00,
                    'haber' => $detalle['haber'] ?? 0.00,
                ]);
            }

            if (empty($asiento->numero_comprobante)) {
                $anio = date('y', strtotime($asiento->fecha ?? date('Y-m-d')));
                $tipo = '10';
                $secuencia = str_pad($asiento->id, 6, '0', STR_PAD_LEFT);

                $asiento->update([
                    'numero_comprobante' => $anio . $tipo . $secuencia
                ]);
            }

            return $asiento;
        });
    }

    public function crearAsientoManual(array $datos)
    {
        return DB::transaction(function () use ($datos) {
            $tempNum = 'T' . time() . rand(10, 99);

            $asiento = AsientoContable::create([
                'empresa_id' => $datos['empresa_id'],
                'usuario_id' => $datos['usuario_id'],
                'fecha' => $datos['fecha'],
                'glosa' => $datos['glosa'],
                'tipo_asiento' => $datos['tipo'] ?? 'traspaso',
                'estado' => 'MAYORIZADO',
                'numero_comprobante' => $tempNum,
                'origen_modulo' => $datos['origen_modulo'] ?? 'tesoreria',
                'origen_id' => $datos['origen_id'] ?? null,
            ]);

            $anio = date('y', strtotime($asiento->fecha));
            $tipoCode = '10';
            $secuencia = str_pad($asiento->id, 6, '0', STR_PAD_LEFT);

            $asiento->update([
                'numero_comprobante' => $anio . $tipoCode . $secuencia
            ]);

            foreach ($datos['detalles'] as $detalle) {
                DetalleAsiento::create([
                    'asiento_id' => $asiento->id,
                    'cuenta_contable' => $detalle['cuenta_contable'],
                    'debe' => $detalle['debe'] ?? 0,
                    'haber' => $detalle['haber'] ?? 0,
                    'fecha' => $datos['fecha'],
                    'tipo_operacion' => $detalle['tipo_operacion']
                ]);
            }

            return $asiento;
        });
    }
}