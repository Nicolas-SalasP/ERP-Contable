<?php

namespace App\Domains\Comercial\Services\Honorarios;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Comercial\Models\TasaRetencionHonorarios;

class RegistrarHonorarioService
{
    public function registrar(int $empresaId, array $datos): HonorarioRecibido
    {
        $anio = (int) date('Y', strtotime($datos['fecha']));
        $tasa = TasaRetencionHonorarios::porAnio($anio);

        if (! $this->rutValido($datos['rut_prestador'])) {
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

    private function rutValido(string $rut): bool
    {
        $rut = trim($rut);
        if (! preg_match('/^\d{7,8}-[\dKk]$/', $rut)) {
            return false;
        }
        [$numero, $dv] = explode('-', $rut);

        return strtoupper($dv) === strtoupper($this->calcularDv((int) $numero));
    }

    private function calcularDv(int $numero): string
    {
        $suma   = 0;
        $factor = 2;
        while ($numero > 0) {
            $suma  += ($numero % 10) * $factor;
            $numero = intdiv($numero, 10);
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $resto = 11 - ($suma % 11);

        return match ($resto) {
            11      => '0',
            10      => 'K',
            default => (string) $resto,
        };
    }
}
