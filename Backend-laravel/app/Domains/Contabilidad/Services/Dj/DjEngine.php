<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\DjEnvio;
use Illuminate\Support\Facades\Storage;

class DjEngine
{
    public function generar(
        DeclaracionJuradaContract $dj,
        int $empresaId,
        int $anio,
        ?int $anio40Horas = null,
    ): DjEnvio {
        $data      = $dj->construir($empresaId, $anio);
        $contenido = $dj->formatearArchivo($data);

        $ruta = "dj/{$empresaId}/{$dj->codigo()}/{$anio}.txt";
        Storage::disk(config('sii.storage.disk', 'sii_xml'))->put($ruta, $contenido);

        $envio = DjEnvio::updateOrCreate(
            ['empresa_id' => $empresaId, 'codigo_dj' => $dj->codigo(), 'anio' => $anio],
            [
                'estado'             => DjEnvio::ESTADO_GENERADO,
                'cantidad_registros' => count($data->lineas),
                'archivo_path'       => $ruta,
                'errores_validacion' => null,
                'anio_40_horas'      => $anio40Horas,
            ]
        );

        return $envio->fresh();
    }

    public function validar(
        DeclaracionJuradaContract $dj,
        DjEnvio $envio,
    ): array {
        if (! $envio->archivo_path) {
            throw DjException::regla('El envío no tiene archivo generado. Genera primero.');
        }

        $data    = $dj->construir($envio->empresa_id, $envio->anio);
        $errores = $dj->validar($data);

        if (empty($errores)) {
            $envio->update(['estado' => DjEnvio::ESTADO_VALIDADO, 'errores_validacion' => null]);
        } else {
            $envio->update(['estado' => DjEnvio::ESTADO_CON_ERRORES, 'errores_validacion' => $errores]);
        }

        return $errores;
    }
}
