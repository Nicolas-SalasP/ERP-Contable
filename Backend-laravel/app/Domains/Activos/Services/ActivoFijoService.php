<?php

namespace App\Domains\Activos\Services;

use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Comercial\Services\FacturaService;
use Illuminate\Support\Facades\DB;
use Exception;

class ActivoFijoService
{
    protected $facturaService;

    public function __construct(FacturaService $facturaService)
    {
        $this->facturaService = $facturaService;
    }

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
                $ultimo = ActivoFijo::where('empresa_id', $datos['empresa_id'])->lockForUpdate()->orderBy('id', 'desc')->first();
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
                ->lockForUpdate()
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

    public function analizarProyecto(int $empresaId, int $proyectoId): array
    {
        $proyecto = ProyectoActivo::where('empresa_id', $empresaId)->findOrFail($proyectoId);
        $facturas = $this->facturaService->obtenerPorProyecto($proyectoId);

        return [
            'id' => $proyecto->id_proyecto,
            'nombre' => $proyecto->nombre,
            'estado' => $proyecto->estado,
            'valor_total_original' => (float) $proyecto->valor_total_original,
            'depreciacion_acumulada' => 0,
            'facturas' => $facturas,
            'vida_util_meses' => $proyecto->vida_util_meses,
            'anio_fabricacion' => $proyecto->anio_fabricacion
        ];
    }

    public function listarFacturasDisponibles(int $empresaId): array
    {
        return $this->facturaService->obtenerFacturasDisponiblesParaProyectos($empresaId);
    }

    public function imputarFacturaAProyecto(int $empresaId, int $proyectoId, array $datos)
    {
        return DB::transaction(function () use ($empresaId, $proyectoId, $datos) {
            $proyecto = ProyectoActivo::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($proyectoId);

            if ($proyecto->estado !== 'EN_CONSTRUCCION') {
                throw new Exception("No se pueden agregar facturas a un proyecto que ya está cerrado o activado.");
            }

            $factura = $this->facturaService->obtenerFacturaPorId($empresaId, $datos['factura_id']);

            if ($factura->proyecto_activo_id != null) {
                throw new Exception("Esta factura ya ha sido asignada a un proyecto anteriormente.");
            }

            if ($datos['monto'] > $factura->monto_neto) {
                throw new Exception("Fraude o Error: El monto a imputar no puede superar el neto de la factura.");
            }

            $this->facturaService->vincularAProyecto($empresaId, $datos['factura_id'], $proyectoId);
            $proyecto->increment('valor_total_original', $datos['monto']);
        });
    }

    public function activarProyecto(int $empresaId, int $usuarioId, int $proyectoId): array
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $proyectoId) {
            $proyecto = ProyectoActivo::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($proyectoId);

            if ($proyecto->estado !== 'EN_CONSTRUCCION') {
                throw new Exception("Este proyecto ya ha sido activado anteriormente.");
            }

            if ($proyecto->valor_total_original <= 0) {
                throw new Exception("No se puede capitalizar un proyecto con costo cero. Impute facturas primero.");
            }

            $proyecto->update(['estado' => 'ACTIVO_OPERATIVO']);

            $activo = $this->registrarActivo([
                'empresa_id' => $empresaId,
                'nombre' => $proyecto->nombre,
                'cuenta_activo_codigo' => '120101',
                'valor_adquisicion' => (float) $proyecto->valor_total_original,
                'fecha_adquisicion' => now()->toDateString(),
                'vida_util_meses' => $proyecto->vida_util_meses,
                'valor_residual' => 1,
                'estado' => 'ACTIVO'
            ]);

            return [
                'id' => $activo->id,
                'codigo' => $activo->codigo,
                'nombre' => $activo->nombre,
                'valor_capitalizado' => (float) $activo->valor_adquisicion,
                'fecha_activacion' => $activo->fecha_adquisicion,
                'estado' => $activo->estado
            ];
        });
    }
}