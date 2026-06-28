<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Core\Models\Empresa;

class Dj1835Service implements DeclaracionJuradaContract
{
    public function codigo(): string
    {
        return '1835';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $empresa = Empresa::findOrFail($empresaId);

        // Facturas del exterior con retenci\xc3\xb3n Art. 59 LIR aplicada (maquilas y servicios)
        $facturas = Factura::where('empresa_id', $empresaId)
            ->where('es_documento_exterior', true)
            ->whereNotNull('retencion_art59')
            ->where('retencion_art59', '>', 0)
            ->whereYear('fecha_emision', $anio)
            ->whereNotIn('estado', ['ANULADA'])
            ->with('proveedor')
            ->get();

        if ($facturas->isEmpty()) {
            throw DjException::regla(
                "No hay facturas del exterior con retenci\xc3\xb3n Art. 59 LIR registradas para el a\xc3\xb1o {$anio}."
            );
        }

        // Agrupa por proveedor + tipo de gasto para una l\xc3\xadnea por combinaci\xc3\xb3n
        $grupos = $facturas->groupBy(fn ($f) => $f->proveedor_id . '|' . ($f->tipo_gasto_art59 ?? 'otros'));

        $lineas = [];
        foreach ($grupos as $clave => $grupo) {
            $primera = $grupo->first();
            // Estrechar el tipo para que PHPStan infiera Factura (no Model gen\xc3\xa9rico).
            if (!$primera instanceof Factura) {
                continue;
            }
            $proveedor = $primera->proveedor;

            $lineas[] = new DjLineaData([
                'rut_proveedor'     => $proveedor !== null ? $proveedor->rut : 'SIN-RUT',
                'razon_social'      => $proveedor !== null ? $proveedor->razon_social : '\xe2\x80\x94',
                'tipo_gasto'        => $primera->tipo_gasto_art59 ?? 'otros',
                'monto_bruto_total' => (int) round((float) $grupo->sum('monto_bruto')),
                'retencion_total'   => (int) round((float) $grupo->sum('retencion_art59')),
                'num_documentos'    => $grupo->count(),
            ]);
        }

        return new DjData(
            codigoDj:  '1835',
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
            $errores[] = 'RUT de empresa vac\xc3\xado.';
        }

        if (empty($data->lineas)) {
            $errores[] = 'No hay retenciones Art. 59 LIR declaradas para el a\xc3\xb1o.';
        }

        $retencionTotal = 0;
        foreach ($data->lineas as $i => $linea) {
            $n = $i + 1;
            $c = $linea->campos;

            if (($c['monto_bruto_total'] ?? 0) <= 0) {
                $errores[] = "Proveedor #{$n} ({$c['rut_proveedor']}): monto bruto es cero o negativo.";
            }
            if (($c['retencion_total'] ?? 0) < 0) {
                $errores[] = "Proveedor #{$n} ({$c['rut_proveedor']}): retenci\xc3\xb3n negativa.";
            }
            $retencionTotal += (int) ($c['retencion_total'] ?? 0);
        }

        if (! empty($data->lineas) && $retencionTotal <= 0) {
            $errores[] = 'La retenci\xc3\xb3n Art. 59 total es cero o negativa. Verificar los montos registrados.';
        }

        return $errores;
    }

    public function formatearArchivo(DjData $data): string
    {
        $lineas = [];

        $lineas[] = implode(';', [
            'A',
            $data->cabecera['rut_empresa'],
            $data->cabecera['razon_social'],
            $data->anio,
        ]);

        foreach ($data->lineas as $linea) {
            $c        = $linea->campos;
            $lineas[] = implode(';', [
                'D',
                $c['rut_proveedor'],
                $c['razon_social'],
                $c['tipo_gasto'],
                $c['monto_bruto_total'],
                $c['retencion_total'],
                $c['num_documentos'],
            ]);
        }

        $totalBruto     = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'monto_bruto_total'));
        $totalRetencion = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'retencion_total'));
        $totalCasos     = count($data->lineas);

        $lineas[] = implode(';', ['T', $totalCasos, $totalBruto, $totalRetencion]);

        return implode("\n", $lineas) . "\n";
    }
}
