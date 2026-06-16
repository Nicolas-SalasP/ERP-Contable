<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Core\Models\Empresa;

class Dj1879Service implements DeclaracionJuradaContract
{
    public function codigo(): string
    {
        return '1879';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $empresa = Empresa::findOrFail($empresaId);

        $honorarios = HonorarioRecibido::where('empresa_id', $empresaId)
            ->delAnio($anio)
            ->get();

        if ($honorarios->isEmpty()) {
            throw DjException::regla("No hay honorarios registrados para el año {$anio}.");
        }

        // Agrupar por RUT prestador para generar una línea anual por cada uno
        $porPrestador = $honorarios->groupBy('rut_prestador');

        $lineas = [];
        foreach ($porPrestador as $rut => $grupo) {
            $primer   = $grupo->first();
            $lineas[] = new DjLineaData([
                'rut_prestador'     => $rut,
                'nombre_prestador'  => $primer->nombre_prestador,
                'monto_bruto_total' => (int) $grupo->sum('monto_bruto'),
                'retencion_total'   => (int) $grupo->sum('monto_retencion'),
                'num_documentos'    => $grupo->count(),
            ]);
        }

        return new DjData(
            codigoDj:  '1879',
            empresaId: $empresaId,
            anio:      $anio,
            cabecera: [
                'rut_empresa'  => $empresa->rut,
                'razon_social' => $empresa->razon_social,
            ],
            lineas: $lineas,
        );
    }

    public function validar(DjData $data): array
    {
        $errores = [];

        if (empty($data->cabecera['rut_empresa'])) {
            $errores[] = 'RUT de empresa vacío.';
        }

        if (empty($data->lineas)) {
            $errores[] = 'No hay prestadores con honorarios registrados en el año.';
        }

        $retencionTotal = 0;
        foreach ($data->lineas as $i => $linea) {
            $n = $i + 1;
            $c = $linea->campos;

            if (empty($c['rut_prestador'])) {
                $errores[] = "Prestador #{$n}: RUT vacío.";
            }
            if (($c['monto_bruto_total'] ?? 0) <= 0) {
                $errores[] = "Prestador #{$n} ({$c['rut_prestador']}): monto bruto es cero o negativo.";
            }
            if (($c['retencion_total'] ?? 0) < 0) {
                $errores[] = "Prestador #{$n}: retención negativa.";
            }
            if (($c['num_documentos'] ?? 0) <= 0) {
                $errores[] = "Prestador #{$n}: sin documentos.";
            }
            $retencionTotal += (int) ($c['retencion_total'] ?? 0);
        }

        // Cuadratura: retención total > 0 cuando hay prestadores
        if (! empty($data->lineas) && $retencionTotal <= 0) {
            $errores[] = 'La retención total del año es cero o negativa. Verificar los montos registrados.';
        }

        return $errores;
    }

    public function formatearArchivo(DjData $data): string
    {
        $lineas = [];

        // Registro cabecera
        $lineas[] = implode(';', [
            'A',
            $data->cabecera['rut_empresa'],
            $data->cabecera['razon_social'],
            $data->anio,
        ]);

        // Registro por prestador
        foreach ($data->lineas as $linea) {
            $c = $linea->campos;
            $lineas[] = implode(';', [
                'D',
                $c['rut_prestador'],
                $c['nombre_prestador'],
                $c['monto_bruto_total'],
                $c['retencion_total'],
                $c['num_documentos'],
            ]);
        }

        // Totales globales
        $totalBruto     = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'monto_bruto_total'));
        $totalRetencion = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'retencion_total'));
        $totalCasos     = count($data->lineas);

        $lineas[] = implode(';', ['T', $totalCasos, $totalBruto, $totalRetencion]);

        return implode("\n", $lineas) . "\n";
    }
}
