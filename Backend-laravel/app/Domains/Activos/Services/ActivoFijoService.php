<?php

namespace App\Domains\Activos\Services;

use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Activos\Models\ProyectoActivo;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Contabilidad\Services\PlanCuentaService;
use Illuminate\Support\Facades\DB;
use Exception;

class ActivoFijoService
{
    protected $facturaService;
    protected $planCuentaService;

    public function __construct(FacturaService $facturaService, PlanCuentaService $planCuentaService)
    {
        $this->facturaService = $facturaService;
        $this->planCuentaService = $planCuentaService;
    }

    public function listarActivos(int $empresaId)
    {
        return ActivoFijo::where('empresa_id', $empresaId)
            ->with(['cuenta'])
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
            $activosOperativos = ActivoFijo::where('empresa_id', $empresaId)
                ->where('estado', 'ACTIVO')
                ->count();

            if ($activosOperativos === 0) {
                throw new Exception("No hay activos fijos operativos.");
            }

            $activos = ActivoFijo::where('empresa_id', $empresaId)
                ->where('estado', 'ACTIVO')
                ->whereRaw('depreciacion_acumulada < (valor_adquisicion - valor_residual)')
                ->where('fecha_adquisicion', '<=', $fechaCalculo)
                ->lockForUpdate()
                ->get();

            $detallesAsiento = [];
            $totalDepreciacionMes = 0;
            $sqlCases = [];
            $sqlBindings = [];
            $idsActivos = [];

            foreach ($activos as $activo) {
                $montoDepreciable = $activo->valor_adquisicion - $activo->valor_residual;
                $cuotaMensual = round($montoDepreciable / $activo->vida_util_meses, 0);

                $saldoPorDepreciar = $montoDepreciable - $activo->depreciacion_acumulada;
                if ($cuotaMensual > $saldoPorDepreciar) {
                    $cuotaMensual = $saldoPorDepreciar;
                }

                if ($cuotaMensual > 0) {
                    if (empty($activo->cuenta_gasto_codigo) || empty($activo->cuenta_depreciacion_codigo)) {
                        throw new Exception("El activo {$activo->codigo} ({$activo->nombre}) no tiene sus cuentas de depreciación configuradas. Edite su ficha contable antes de depreciar el mes.");
                    }

                    $sqlCases[] = "WHEN ? THEN depreciacion_acumulada + ?";
                    $sqlBindings[] = $activo->id;
                    $sqlBindings[] = $cuotaMensual;
                    $idsActivos[] = $activo->id;

                    $detallesAsiento[] = [
                        'cuenta_contable' => $activo->cuenta_gasto_codigo,
                        'debe' => $cuotaMensual,
                        'haber' => 0,
                        'glosa_detalle' => "Depreciación {$activo->codigo} - {$activo->nombre}"
                    ];
                    $detallesAsiento[] = [
                        'cuenta_contable' => $activo->cuenta_depreciacion_codigo,
                        'debe' => 0,
                        'haber' => $cuotaMensual,
                        'glosa_detalle' => "Depreciación Acum. {$activo->codigo}"
                    ];

                    $totalDepreciacionMes += $cuotaMensual;
                }
            }

            if ($totalDepreciacionMes == 0) {
                return ['mensaje' => 'Los activos ya han alcanzado su valor residual mínimo.', 'asiento_comprobante' => null];
            }

            try {
                if (!empty($idsActivos)) {
                    $casesStr = implode(' ', $sqlCases);
                    $placeholders = implode(',', array_fill(0, count($idsActivos), '?'));
                    $sqlBindings = array_merge($sqlBindings, $idsActivos);

                    DB::statement("UPDATE activos_fijos SET depreciacion_acumulada = CASE id {$casesStr} ELSE depreciacion_acumulada END WHERE id IN ({$placeholders})", $sqlBindings);
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
                $comprobante = $asiento->numero_comprobante;
                $mensaje = "Depreciación calculada correctamente. Total mes: $" . number_format($totalDepreciacionMes, 0, ',', '.');
            } catch (Exception $e) {
                $comprobante = null;
                $mensaje = "Depreciación física registrada, pero el asiento contable falló: " . $e->getMessage();
            }

            return [
                'mensaje' => $mensaje,
                'asiento_comprobante' => $comprobante
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
            'anio_fabricacion' => $proyecto->anio_fabricacion,
            'tipo_activo_id' => $proyecto->tipo_activo_id,
            'cuenta_depreciacion_id' => $proyecto->cuenta_depreciacion_id,
            'cuenta_gasto_id' => $proyecto->cuenta_gasto_id
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
                throw new Exception("El proyecto está cerrado. No se pueden imputar más costos.");
            }

            $factura = $this->facturaService->obtenerFacturaPorId($empresaId, $datos['factura_id']);

            if ($factura->proyecto_activo_id != null) {
                throw new Exception("La factura N°{$factura->numero_factura} ya está vinculada a otro proyecto.");
            }

            $montoImputar = round((float) $datos['monto'], 2);
            $netoReal = round((float) $factura->monto_neto, 2);

            if (abs($montoImputar - $netoReal) > 0.01) {
                throw new Exception("Monto Incorrecto: Para capitalizar este activo, debe imputar el 100% del valor neto ($" . number_format($netoReal, 0, ',', '.') . "). El IVA no es capitalizable.");
            }

            $this->facturaService->vincularAProyecto($empresaId, $datos['factura_id'], $proyectoId);
            $proyecto->increment('valor_total_original', $montoImputar);
        });
    }

    public function activarProyecto(int $empresaId, int $usuarioId, int $proyectoId): array
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $proyectoId) {
            $proyecto = ProyectoActivo::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($proyectoId);
            if (!$proyecto->tipo_activo_id || !$proyecto->cuenta_depreciacion_id || !$proyecto->cuenta_gasto_id) {
                throw new Exception("Configuración Incompleta: El proyecto requiere asignar las 3 cuentas contables (Activo, Depreciación y Gasto) antes de ser capitalizado.");
            }

            if ($proyecto->estado !== 'EN_CONSTRUCCION') {
                throw new Exception("Este proyecto ya se encuentra activo u operativo.");
            }

            if ($proyecto->valor_total_original <= 0) {
                throw new Exception("No se puede activar un proyecto sin costos imputados. Agregue facturas primero.");
            }

            $cuentaActivo = $this->planCuentaService->obtenerCuentaPorId($empresaId, (int) $proyecto->tipo_activo_id);
            $cuentaDepre = $this->planCuentaService->obtenerCuentaPorId($empresaId, (int) $proyecto->cuenta_depreciacion_id);
            $cuentaGasto = $this->planCuentaService->obtenerCuentaPorId($empresaId, (int) $proyecto->cuenta_gasto_id);

            if (!$cuentaActivo || !$cuentaDepre || !$cuentaGasto) {
                throw new Exception("Error de Integridad: Una o más cuentas configuradas en el proyecto ya no existen en el plan de cuentas.");
            }

            $proyecto->update(['estado' => 'ACTIVO_OPERATIVO']);

            $activo = $this->registrarActivo([
                'empresa_id' => $empresaId,
                'nombre' => $proyecto->nombre,
                'cuenta_activo_codigo' => $cuentaActivo->codigo,
                'cuenta_depreciacion_codigo' => $cuentaDepre->codigo,
                'cuenta_gasto_codigo' => $cuentaGasto->codigo,
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

    public function actualizarProyecto(int $empresaId, int $proyectoId, array $datos): ProyectoActivo
    {
        $proyecto = ProyectoActivo::where('empresa_id', $empresaId)->findOrFail($proyectoId);

        if ($proyecto->estado !== 'EN_CONSTRUCCION') {
            throw new Exception("No se puede editar un proyecto que ya ha sido activado o capitalizado.");
        }

        $proyecto->update($datos);

        return $proyecto;
    }

    public function darDeBaja(int $empresaId, int $usuarioId, int $activoId, array $datos = [])
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $activoId, $datos) {
            $activo = ActivoFijo::where('empresa_id', $empresaId)->lockForUpdate()->findOrFail($activoId);

            if ($activo->estado !== 'ACTIVO') {
                throw new Exception("Solo se pueden dar de baja activos que se encuentren operativos.");
            }

            $valorAdquisicion = (float) $activo->valor_adquisicion;
            $depreciacionAcumulada = (float) $activo->depreciacion_acumulada;
            $valorLibro = $valorAdquisicion - $depreciacionAcumulada;

            $cuentaPerdida = $this->planCuentaService->obtenerCuentaPorCodigo($empresaId, '999999');

            if (!$cuentaPerdida) {
                throw new Exception("Falta configuración: No se encontró la cuenta '999999 - Cancelaciones / Ajustes' para reconocer la pérdida del valor libro.");
            }

            $detallesAsiento = [];

            if ($depreciacionAcumulada > 0) {
                $detallesAsiento[] = [
                    'cuenta_contable' => $activo->cuenta_depreciacion_codigo,
                    'debe' => $depreciacionAcumulada,
                    'haber' => 0,
                    'glosa_detalle' => "Reverso Deprec. Acum. por Baja Activo {$activo->codigo}"
                ];
            }

            if ($valorLibro > 0) {
                $detallesAsiento[] = [
                    'cuenta_contable' => $cuentaPerdida->codigo,
                    'debe' => $valorLibro,
                    'haber' => 0,
                    'glosa_detalle' => "Pérdida por Baja de Activo {$activo->codigo}"
                ];
            }

            $detallesAsiento[] = [
                'cuenta_contable' => $activo->cuenta_activo_codigo,
                'debe' => 0,
                'haber' => $valorAdquisicion,
                'glosa_detalle' => "Baja Activo {$activo->codigo} - " . ($datos['motivo'] ?? 'Obsolescencia/Retiro')
            ];

            $asientoService = app(AsientoContableService::class);
            $cabecera = [
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'fecha' => now()->toDateString(),
                'glosa' => "Baja de Activo Fijo {$activo->codigo} - {$activo->nombre}",
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'activos',
                'estado' => 'MAYORIZADO'
            ];

            $asiento = $asientoService->registrarAsiento($cabecera, $detallesAsiento);

            $activo->update([
                'estado' => 'DADO_DE_BAJA',
                'descripcion' => ($activo->descripcion ? $activo->descripcion . " | " : "") . "BAJA: " . ($datos['motivo'] ?? 'Sin especificar')
            ]);

            return [
                'mensaje' => "Activo dado de baja exitosamente. (Asiento N°{$asiento->numero_comprobante})",
                'activo' => $activo
            ];
        });
    }
}