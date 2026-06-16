<?php

namespace App\Domains\Rrhh\Services\Lre;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\LreEnvio;

/**
 * Prepara los metadatos necesarios para la descarga del archivo LRE.
 * Retorna ['ruta' => string, 'nombre_archivo' => string].
 */
class PrepararDescargaService
{
    public function prepararDescarga(LreEnvio $lre): array
    {
        if (! $lre->archivo_path) {
            throw RrhhException::regla('El LRE no tiene archivo generado. Genera el LRE primero.');
        }

        $mesFormato    = str_pad((string) $lre->mes, 2, '0', STR_PAD_LEFT);
        $nombreArchivo = "LRE_{$lre->anio}_{$mesFormato}.txt";

        return [
            'ruta'           => $lre->archivo_path,
            'nombre_archivo' => $nombreArchivo,
        ];
    }
}
