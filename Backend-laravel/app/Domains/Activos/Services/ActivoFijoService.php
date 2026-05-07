<?php

namespace App\Domains\Activos\Services;

use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class ActivoFijoService
{
    public function listarActivos(int $empresaId)
    {
        return ActivoFijo::where('empresa_id', $empresaId)
            ->whereIn('estado', ['ACTIVO', 'DADO_DE_BAJA'])
            ->get();
    }

    public function listarPendientes(int $empresaId)
    {
        return ActivoFijo::where('empresa_id', $empresaId)
            ->where('estado', 'PENDIENTE')
            ->get();
    }

    public function registrarActivo(array $datos): ActivoFijo
    {
        return DB::transaction(function () use ($datos) {

            if (empty($datos['codigo'])) {
                $ultimo = ActivoFijo::where('empresa_id', $datos['empresa_id'])->orderBy('id', 'desc')->first();
                $num = $ultimo ? $ultimo->id + 1 : 1;
                $datos['codigo'] = 'AF-' . str_pad($num, 5, '0', STR_PAD_LEFT);
            }

            $datos['estado'] = $datos['estado'] ?? 'ACTIVO';

            return ActivoFijo::create($datos);
        });
    }

    public function listarProyectos(int $empresaId)
    {
        return ProyectoActivo::where('empresa_id', $empresaId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function registrarProyecto(array $datos): ProyectoActivo
    {
        $datos['estado'] = 'EN_CONSTRUCCION';
        $datos['valor_total_original'] = 0;
        
        return ProyectoActivo::create($datos);
    }

    public function depreciarMes(int $empresaId, int $usuarioId, string $mesAnio)
    {
        $fechaCalculo = date('Y-m-t', strtotime($mesAnio . '-01'));

        return DB::transaction(function () use ($empresaId, $usuarioId, $fechaCalculo) {
            
            $activos = ActivoFijo::where('empresa_id', $empresaId)
                ->where('estado', 'ACTIVO')
                ->whereRaw('depreciacion_acumulada < (valor_adquisicion - valor_residual)')
                ->where('fecha_adquisicion', '<=', $fechaCalculo)
                ->get();

            if ($activos->isEmpty()) {
                throw new Exception("No hay activos fijos operativos que requieran depreciación para este período.");
            }

            $detallesAsiento = [];
            $totalDepreciacionMes = 0;

            foreach ($activos as $activo) {
                $montoDepreciable = $activo->valor_adquisicion - $activo->valor_residual;
                $cuotaMensual = round($montoDepreciable / $activo->vida_util_meses, 0);
                
                $saldoPorDepreciar = $montoDepreciable - $activo->depreciacion_acumulada;
                if ($cuotaMensual > $saldoPorDepreciar) {
                    $cuotaMensual = $saldoPorDepreciar;
                }

                if ($cuotaMensual > 0) {
                    $activo->increment('depreciacion_acumulada', $cuotaMensual);

                    $detallesAsiento[] = [
                        'cuenta_contable' => $activo->cuenta_gasto_codigo ?? '410101',
                        'debe' => $cuotaMensual,
                        'haber' => 0,
                        'glosa_detalle' => "Depreciación {$activo->codigo} - {$activo->nombre}"
                    ];
                    $detallesAsiento[] = [
                        'cuenta_contable' => $activo->cuenta_depreciacion_codigo ?? '120102',
                        'debe' => 0,
                        'haber' => $cuotaMensual,
                        'glosa_detalle' => "Depreciación Acum. {$activo->codigo}"
                    ];

                    $totalDepreciacionMes += $cuotaMensual;
                }
            }

            if ($totalDepreciacionMes == 0) {
                return ['mensaje' => 'Los activos ya han alcanzado su valor residual mínimo.', 'asiento_id' => null];
            }

            $asientoService = app(AsientoContableService::class);
            
            $cabecera = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'fecha' => $fechaCalculo,
                'glosa' => "Centralización Depreciación Activos Fijos - " . date('m/Y', strtotime($fechaCalculo)),
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'activos',
                'estado' => 'MAYORIZADO'
            ];

            $asiento = $asientoService->registrarAsiento($cabecera, $detallesAsiento);

            return [
                'mensaje' => "Depreciación calculada correctamente. Total mes: $" . number_format($totalDepreciacionMes, 0, ',', '.'),
                'asiento_comprobante' => $asiento->numero_comprobante
            ];
        });
    }
}