<?php

namespace App\Domains\Contabilidad\Services\Dj;

use App\Domains\Contabilidad\Contracts\DeclaracionJuradaContract;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Core\Models\Empresa;
use Illuminate\Support\Facades\DB;

class Dj1926Service implements DeclaracionJuradaContract
{
    public function codigo(): string
    {
        return '1926';
    }

    public function construir(int $empresaId, int $anio): DjData
    {
        $empresa = Empresa::findOrFail($empresaId);

        // Agrega detalles de asientos del año donde la cuenta está marcada como gasto rechazado
        $resultados = DB::table('detalles_asiento as da')
            ->join('asientos_contables as ac', 'ac.id', '=', 'da.asiento_id')
            ->join('plan_cuentas as pc', function ($join) use ($empresaId) {
                $join->on('pc.codigo', '=', 'da.cuenta_contable')
                    ->where('pc.empresa_id', $empresaId)
                    ->where('pc.es_gasto_rechazado', true);
            })
            ->where('ac.empresa_id', $empresaId)
            ->where('ac.estado', 'MAYORIZADO')
            ->whereYear('ac.fecha', $anio)
            ->select('da.cuenta_contable', 'pc.nombre', DB::raw('SUM(da.debe) as total'))
            ->groupBy('da.cuenta_contable', 'pc.nombre')
            ->get();

        if ($resultados->isEmpty()) {
            throw DjException::regla(
                "No hay gastos rechazados en cuentas marcadas como no deducibles para el año {$anio}. "
                . 'Marque las cuentas correspondientes en el Plan de Cuentas antes de generar.'
            );
        }

        $lineas = $resultados->map(fn ($r) => new DjLineaData([
            'codigo_cuenta' => $r->cuenta_contable,
            'nombre_cuenta' => $r->nombre,
            'monto_total'   => (int) $r->total,
        ]))->all();

        return new DjData(
            codigoDj:  '1926',
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
            $errores[] = 'No hay gastos rechazados declarados para el año.';
        }

        foreach ($data->lineas as $i => $linea) {
            $n = $i + 1;
            $c = $linea->campos;

            if (($c['monto_total'] ?? 0) <= 0) {
                $errores[] = "Gasto #{$n} ({$c['codigo_cuenta']}): monto es cero o negativo.";
            }
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
                $c['codigo_cuenta'],
                $c['nombre_cuenta'],
                $c['monto_total'],
            ]);
        }

        $totalMonto   = array_sum(array_column(array_map(fn ($l) => $l->campos, $data->lineas), 'monto_total'));
        $totalCuentas = count($data->lineas);

        $lineas[] = implode(';', ['T', $totalCuentas, $totalMonto]);

        return implode("\n", $lineas) . "\n";
    }
}
