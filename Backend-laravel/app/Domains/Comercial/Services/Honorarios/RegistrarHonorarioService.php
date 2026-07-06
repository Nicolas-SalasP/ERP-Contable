<?php

namespace App\Domains\Comercial\Services\Honorarios;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Comercial\Models\TasaRetencionHonorarios;
use App\Domains\Sii\Support\RutHelper;

class RegistrarHonorarioService
{
    public function registrar(int $empresaId, array $datos): HonorarioRecibido
    {
        $anio = (int) date('Y', strtotime($datos['fecha']));
        $tasa = TasaRetencionHonorarios::porAnio($anio);

        if (! RutHelper::validar($datos['rut_prestador'])) {
            throw ComercialException::regla("RUT del prestador inválido: {$datos['rut_prestador']}");
        }

        $bruto     = (int) $datos['monto_bruto'];
        $retencion = (int) round($bruto * ($tasa->tasa_pct / 100));
        $liquido   = $bruto - $retencion;

        return HonorarioRecibido::create([
            'empresa_id'         => $empresaId,
            'proveedor_id'       => $datos['proveedor_id'] ?? null,
            'rut_prestador'      => $datos['rut_prestador'],
            'nombre_prestador'   => $datos['nombre_prestador'],
            'numero_boleta'      => $datos['numero_boleta'] ?? null,
            'fecha'              => $datos['fecha'],
            'monto_bruto'        => $bruto,
            'tasa_retencion_pct' => $tasa->tasa_pct,
            'monto_retencion'    => $retencion,
            'monto_liquido'      => $liquido,
        ]);
    }
}
