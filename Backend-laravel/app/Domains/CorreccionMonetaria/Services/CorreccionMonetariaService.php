<?php

namespace App\Domains\CorreccionMonetaria\Services;

use App\Domains\CorreccionMonetaria\Models\CmIndiceIpc;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta;
use App\Domains\CorreccionMonetaria\Models\CmEjecucion;
use App\Domains\CorreccionMonetaria\Providers\IpcProviderInterface;
use App\Domains\CorreccionMonetaria\Providers\ManualIpcProvider;
use App\Domains\Activos\Models\ActivoFijo;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Support\Facades\DB;
use Exception;

class CorreccionMonetariaService
{
    public function __construct(
        private readonly AsientoContableService $asientoService,
        private readonly IpcProviderInterface $ipcProvider,
    ) {}

    public function guardarIndice(int $usuarioId, int $anio, int $mes, float $variacion, ?string $observacion = null): CmIndiceIpc
    {
        if ($mes < 1 || $mes > 12) {
            throw new Exception('El mes debe estar entre 1 y 12.');
        }
        if ($anio < 2000 || $anio > 2100) {
            throw new Exception('El año debe estar entre 2000 y 2100.');
        }

        $indice = CmIndiceIpc::updateOrCreate(
            ['anio' => $anio, 'mes' => $mes],
            [
                'variacion_mensual'     => $variacion,
                'factor_multiplicador'  => round(1 + ($variacion / 100), 6),
                'fuente'                => 'manual',
                'observacion'           => $observacion,
                'creado_por_usuario_id' => $usuarioId,
            ]
        );

        $this->recalcularAcumuladosAnio($anio);

        if ($this->ipcProvider instanceof ManualIpcProvider) {
            for ($m = $mes; $m <= 12; $m++) {
                $this->ipcProvider->invalidarCache($anio, $m);
            }
        }

        return $indice->fresh();
    }

    public function obtenerIndicesAnio(int $anio): array
    {
        $indices = CmIndiceIpc::delAnio($anio)->get()->keyBy('mes');
        $resultado = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $indice = $indices->get($mes);
            $resultado[] = [
                'mes'                 => $mes,
                'nombre_mes'          => $this->nombreMes($mes),
                'anio'                => $anio,
                'cargado'             => $indice !== null,
                'variacion_mensual'   => $indice ? (float) $indice->variacion_mensual : null,
                'variacion_acumulada' => $indice ? (float) $indice->variacion_acumulada_anual : null,
                'factor_multiplicador'=> $indice ? (float) $indice->factor_multiplicador : null,
                'fuente'              => $indice?->fuente,
                'observacion'         => $indice?->observacion,
                'updated_at'          => $indice?->updated_at,
            ];
        }

        return $resultado;
    }

    public function obtenerConfiguracion(int $empresaId): array
    {
        $config = CmConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        if (!$config) {
            throw new Exception('La empresa no tiene configuración de Corrección Monetaria. Ejecute el seeder CmCuentasMaestroSeeder.');
        }

        return [
            'aplica_cm'                  => $config->aplica_cm,
            'modalidad'                  => $config->modalidad,
            'mes_cierre'                 => $config->mes_cierre,
            'nombre_mes_cierre'          => $config->nombre_mes_cierre,
            'cuenta_activos_codigo'      => $config->cuenta_activos_codigo,
            'cuenta_depreciacion_codigo' => $config->cuenta_depreciacion_codigo,
            'cuenta_patrimonio_codigo'   => $config->cuenta_patrimonio_codigo,
            'cuenta_existencias_codigo'  => $config->cuenta_existencias_codigo,
            'cuenta_pasivos_codigo'      => $config->cuenta_pasivos_codigo,
            'activo'                     => $config->activo,
            'proveedor_ipc'              => $this->ipcProvider->getNombre(),
        ];
    }

    public function actualizarConfiguracion(int $empresaId, array $datos): CmConfiguracionEmpresa
    {
        $config = CmConfiguracionEmpresa::where('empresa_id', $empresaId)->firstOrFail();

        $permitidos = [
            'aplica_cm', 'modalidad', 'mes_cierre',
            'cuenta_activos_codigo', 'cuenta_depreciacion_codigo',
            'cuenta_patrimonio_codigo', 'cuenta_existencias_codigo',
            'cuenta_pasivos_codigo', 'activo',
        ];

        $config->update(array_intersect_key($datos, array_flip($permitidos)));

        return $config->fresh();
    }

    public function obtenerCuentasConfiguracion(int $empresaId): array
    {
        $cuentas = CmConfiguracionCuenta::where('empresa_id', $empresaId)->get();
        $codigos = $cuentas->pluck('cuenta_codigo')->toArray();

        $nombres = PlanCuenta::where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->pluck('nombre', 'codigo');

        return $cuentas->map(fn($c) => [
            'id'              => $c->id,
            'cuenta_codigo'   => $c->cuenta_codigo,
            'nombre_cuenta'   => $nombres[$c->cuenta_codigo] ?? $c->cuenta_codigo,
            'rol_cm'          => $c->rol_cm,
            'label_rol'       => $c->label_rol,
            'aplica'          => $c->aplica,
            'factor_override' => $c->factor_override,
        ])->values()->toArray();
    }

    public function actualizarCuentasConfiguracion(int $empresaId, array $cuentas): void
    {
        foreach ($cuentas as $data) {
            CmConfiguracionCuenta::where('empresa_id', $empresaId)
                ->where('cuenta_codigo', $data['cuenta_codigo'])
                ->update([
                    'aplica'          => $data['aplica'] ?? true,
                    'factor_override' => $data['factor_override'] ?? null,
                ]);
        }
    }

    public function agregarCuentaConfiguracion(int $empresaId, string $cuentaCodigo, string $rolCm): CmConfiguracionCuenta
    {
        $roles = [
            CmConfiguracionCuenta::ROL_ACTIVO_NO_MONETARIO,
            CmConfiguracionCuenta::ROL_DEPRECIACION_ACUMULADA,
            CmConfiguracionCuenta::ROL_INVENTARIO,
            CmConfiguracionCuenta::ROL_PATRIMONIO_CAPITAL,
            CmConfiguracionCuenta::ROL_PASIVO_NO_MONETARIO,
        ];

        if (!in_array($rolCm, $roles)) {
            throw new Exception("Rol CM inválido: {$rolCm}.");
        }

        if (!PlanCuenta::where('empresa_id', $empresaId)->where('codigo', $cuentaCodigo)->exists()) {
            throw new Exception("La cuenta {$cuentaCodigo} no existe en el plan de la empresa.");
        }

        return CmConfiguracionCuenta::updateOrCreate(
            ['empresa_id' => $empresaId, 'cuenta_codigo' => $cuentaCodigo],
            ['rol_cm' => $rolCm, 'aplica' => true]
        );
    }

    public function estadoPeriodo(int $empresaId, int $mes, int $anio): array
    {
        $config = CmConfiguracionEmpresa::where('empresa_id', $empresaId)->first();
        $yaEjecutada = CmEjecucion::where('empresa_id', $empresaId)
            ->where('periodo_mes', $mes)
            ->where('periodo_anio', $anio)
            ->where('estado', 'ejecutada')
            ->exists();

        $tieneIpc = $this->ipcProvider->tieneIndice($anio, $mes);

        $puedeEjecutar = $config
            && $config->activo
            && $config->aplica_cm
            && $config->puedeEjecutarMes($mes)
            && $tieneIpc
            && !$yaEjecutada;

        return [
            'mes'                    => $mes,
            'anio'                   => $anio,
            'nombre_mes'             => $this->nombreMes($mes),
            'ya_ejecutada'           => $yaEjecutada,
            'tiene_ipc'              => $tieneIpc,
            'puede_ejecutar'         => $puedeEjecutar,
            'puede_simular'          => $tieneIpc && $config?->activo,
            'aplica_cm'              => $config?->aplica_cm ?? false,
            'modalidad'              => $config?->modalidad,
            'mes_cierre'             => $config?->mes_cierre,
            'bloqueado_por_modalidad'=> $config && !$config->puedeEjecutarMes($mes),
        ];
    }

    public function simular(int $empresaId, int $mes, int $anio): array
    {
        $config = $this->validarYObtenerConfig($empresaId, $mes, $anio, soloSimular: true);
        ['variacion' => $variacion, 'factor' => $factor, 'tipo' => $tipo] = $this->resolverFactor($config, $mes, $anio);
        $fechaCalculo = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

        ['lineas' => $lineas, 'detalles' => $detalles, 'totales' => $totales] =
            $this->calcularLineas($empresaId, $config, $variacion, $fechaCalculo);

        return [
            'periodo'        => ['mes' => $mes, 'anio' => $anio, 'nombre_mes' => $this->nombreMes($mes)],
            'tipo'           => $tipo,
            'variacion_pct'  => $variacion,
            'factor'         => $factor,
            'proveedor_ipc'  => $this->ipcProvider->getNombre(),
            'modalidad'      => $config->modalidad,
            'lineas'         => $lineas,
            'asiento_preview'=> $detalles,
            'totales'        => $totales,
            'es_simulacion'  => true,
        ];
    }

    public function ejecutar(int $empresaId, int $usuarioId, int $mes, int $anio): array
    {
        return DB::transaction(function () use ($empresaId, $usuarioId, $mes, $anio) {
            $config = $this->validarYObtenerConfig($empresaId, $mes, $anio, soloSimular: false);
            ['variacion' => $variacion, 'factor' => $factor, 'tipo' => $tipo] = $this->resolverFactor($config, $mes, $anio);
            $fechaCalculo = date('Y-m-t', mktime(0, 0, 0, $mes, 1, $anio));

            ['lineas' => $lineas, 'detalles' => $detalles, 'totales' => $totales] =
                $this->calcularLineas($empresaId, $config, $variacion, $fechaCalculo);

            if ($totales['neto'] <= 0) {
                throw new Exception('No hay ajustes de Corrección Monetaria para este período. Verifique que las cuentas configuradas tengan saldo.');
            }

            $glosa = "Corrección Monetaria {$tipo} - {$this->nombreMes($mes)} {$anio} (IPC: {$variacion}%)";

            $asiento = $this->asientoService->registrarAsiento([
                'empresa_id'    => $empresaId,
                'usuario_id'    => $usuarioId,
                'fecha'         => $fechaCalculo,
                'glosa'         => $glosa,
                'tipo_asiento'  => 'traspaso',
                'origen_modulo' => 'correccion_monetaria',
                'estado'        => 'MAYORIZADO',
            ], $detalles);

            $this->actualizarActivosFijos($empresaId, $factor, $mes, $anio);

            $ejecucion = CmEjecucion::create([
                'empresa_id'                => $empresaId,
                'periodo_mes'               => $mes,
                'periodo_anio'              => $anio,
                'tipo'                      => $tipo,
                'estado'                    => 'ejecutada',
                'factor_ipc_utilizado'      => $factor,
                'variacion_porcentual'      => $variacion,
                'total_ajuste_activos'      => $totales['activos'],
                'total_ajuste_depreciacion' => $totales['depreciacion'],
                'total_ajuste_patrimonio'   => $totales['patrimonio'],
                'total_ajuste_existencias'  => $totales['existencias'],
                'total_ajuste_pasivos'      => $totales['pasivos'],
                'total_cm_neto'             => $totales['neto'],
                'asiento_id'                => $asiento->id,
                'usuario_id'               => $usuarioId,
            ]);

            return [
                'ejecucion_id'        => $ejecucion->id,
                'asiento_comprobante' => $asiento->numero_comprobante,
                'total_cm_neto'       => $totales['neto'],
                'tipo'                => $tipo,
                'variacion_pct'       => $variacion,
                'totales'             => $totales,
                'mensaje'             => "Corrección Monetaria {$tipo} ejecutada. Total ajustado: $" . number_format($totales['neto'], 0, ',', '.'),
            ];
        });
    }

    public function obtenerHistorial(int $empresaId, ?int $anio = null): array
    {
        $query = CmEjecucion::where('empresa_id', $empresaId)
            ->with(['asiento', 'usuario'])
            ->orderByDesc('periodo_anio')
            ->orderByDesc('periodo_mes');

        if ($anio) {
            $query->where('periodo_anio', $anio);
        }

        return $query->get()->map(fn($e) => [
            'id'                  => $e->id,
            'periodo'             => $e->label_periodo,
            'mes'                 => $e->periodo_mes,
            'anio'                => $e->periodo_anio,
            'tipo'                => $e->tipo,
            'estado'              => $e->estado,
            'variacion_pct'       => (float) $e->variacion_porcentual,
            'total_cm_neto'       => (float) $e->total_cm_neto,
            'total_activos'       => (float) $e->total_ajuste_activos,
            'total_depreciacion'  => (float) $e->total_ajuste_depreciacion,
            'total_patrimonio'    => (float) $e->total_ajuste_patrimonio,
            'total_existencias'   => (float) $e->total_ajuste_existencias,
            'total_pasivos'       => (float) $e->total_ajuste_pasivos,
            'asiento_comprobante' => $e->asiento?->numero_comprobante,
            'asiento_id'          => $e->asiento_id,
            'usuario'             => $e->usuario?->nombre,
            'fecha'               => $e->created_at?->format('d/m/Y H:i'),
        ])->values()->toArray();
    }

    private function validarYObtenerConfig(int $empresaId, int $mes, int $anio, bool $soloSimular): CmConfiguracionEmpresa
    {
        $config = CmConfiguracionEmpresa::where('empresa_id', $empresaId)->first();

        if (!$config || !$config->activo) {
            throw new Exception('La Corrección Monetaria no está configurada o está desactivada para esta empresa.');
        }

        if (!$this->ipcProvider->tieneIndice($anio, $mes)) {
            throw new Exception("No hay índice IPC cargado para {$this->nombreMes($mes)} {$anio}. Ingrese el índice antes de continuar.");
        }

        if ($soloSimular) {
            return $config;
        }

        if (!$config->aplica_cm) {
            throw new Exception('Esta empresa opera bajo régimen 14_D8 (Pro Pyme Transparente) y no aplica Corrección Monetaria obligatoria.');
        }

        if (!$config->puedeEjecutarMes($mes)) {
            throw new Exception("En modalidad anual, la ejecución solo está permitida en {$config->nombre_mes_cierre}. Use el simulador para ver proyecciones de otros meses.");
        }

        $yaEjecutada = CmEjecucion::where('empresa_id', $empresaId)
            ->where('periodo_mes', $mes)
            ->where('periodo_anio', $anio)
            ->where('estado', 'ejecutada')
            ->exists();

        if ($yaEjecutada) {
            throw new Exception("La Corrección Monetaria de {$this->nombreMes($mes)} {$anio} ya fue ejecutada y contabilizada.");
        }

        return $config;
    }

    private function resolverFactor(CmConfiguracionEmpresa $config, int $mes, int $anio): array
    {
        if ($config->modalidad === 'anual') {
            $variacion = $this->ipcProvider->getVariacionAcumulada($anio, $mes) ?? 0.0;
            $tipo = 'anual';
        } else {
            $variacion = $this->ipcProvider->getVariacionMensual($anio, $mes) ?? 0.0;
            $tipo = 'mensual';
        }

        return [
            'variacion' => (float) $variacion,
            'factor'    => round(1 + ($variacion / 100), 6),
            'tipo'      => $tipo,
        ];
    }

    private function calcularLineas(int $empresaId, CmConfiguracionEmpresa $config, float $variacion, string $fechaHasta): array
    {
        $cuentas = CmConfiguracionCuenta::where('empresa_id', $empresaId)
            ->where('aplica', true)
            ->get();

        if ($cuentas->isEmpty()) {
            throw new Exception('No hay cuentas configuradas para Corrección Monetaria. Configure las cuentas en el módulo.');
        }

        $codigos = $cuentas->pluck('cuenta_codigo')->toArray();
        $nombres = PlanCuenta::where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->pluck('nombre', 'codigo');

        $saldos = $this->getSaldosCuentas($empresaId, $codigos, $fechaHasta);

        $lineas     = [];
        $detalles   = [];
        $totales    = ['activos' => 0, 'depreciacion' => 0, 'patrimonio' => 0, 'existencias' => 0, 'pasivos' => 0, 'neto' => 0];

        foreach ($cuentas as $cuenta) {
            $saldoBruto          = $saldos[$cuenta->cuenta_codigo] ?? 0.0;
            $variacionEfectiva   = $cuenta->factor_override !== null ? (float)$cuenta->factor_override : $variacion;
            $ROL                 = $cuenta->rol_cm;

            $saldoAjustable = match ($ROL) {
                CmConfiguracionCuenta::ROL_ACTIVO_NO_MONETARIO    => max(0.0, $saldoBruto),
                CmConfiguracionCuenta::ROL_INVENTARIO              => max(0.0, $saldoBruto),
                CmConfiguracionCuenta::ROL_DEPRECIACION_ACUMULADA  => max(0.0, -$saldoBruto),
                CmConfiguracionCuenta::ROL_PATRIMONIO_CAPITAL      => max(0.0, -$saldoBruto),
                CmConfiguracionCuenta::ROL_PASIVO_NO_MONETARIO     => max(0.0, -$saldoBruto),
                default => 0.0,
            };

            if ($saldoAjustable <= 0) {
                continue;
            }

            $ajuste = (int) round($saldoAjustable * ($variacionEfectiva / 100), 0);
            if ($ajuste <= 0) {
                continue;
            }

            $nombre = $nombres[$cuenta->cuenta_codigo] ?? $cuenta->cuenta_codigo;

            $lineas[] = [
                'cuenta_codigo'    => $cuenta->cuenta_codigo,
                'nombre_cuenta'    => $nombre,
                'rol_cm'           => $ROL,
                'label_rol'        => $cuenta->label_rol,
                'saldo_ajustable'  => $saldoAjustable,
                'variacion_usada'  => $variacionEfectiva,
                'ajuste'           => $ajuste,
            ];

            if ($ROL === CmConfiguracionCuenta::ROL_ACTIVO_NO_MONETARIO) {
                $totales['activos'] += $ajuste;
                $detalles[] = ['cuenta_contable' => $cuenta->cuenta_codigo,       'debe' => $ajuste, 'haber' => 0,      'glosa_detalle' => "CM Activo: {$nombre}"];
                $detalles[] = ['cuenta_contable' => $config->cuenta_activos_codigo,'debe' => 0,       'haber' => $ajuste,'glosa_detalle' => "CM Resultado Activos No Monetarios"];
            } elseif ($ROL === CmConfiguracionCuenta::ROL_INVENTARIO) {
                $totales['existencias'] += $ajuste;
                $detalles[] = ['cuenta_contable' => $cuenta->cuenta_codigo,            'debe' => $ajuste, 'haber' => 0,      'glosa_detalle' => "CM Existencia: {$nombre}"];
                $detalles[] = ['cuenta_contable' => $config->cuenta_existencias_codigo,'debe' => 0,       'haber' => $ajuste,'glosa_detalle' => "CM Resultado Existencias"];
            } elseif ($ROL === CmConfiguracionCuenta::ROL_DEPRECIACION_ACUMULADA) {
                $totales['depreciacion'] += $ajuste;
                $detalles[] = ['cuenta_contable' => $config->cuenta_depreciacion_codigo,'debe' => $ajuste, 'haber' => 0,      'glosa_detalle' => "CM Resultado Depreciación"];
                $detalles[] = ['cuenta_contable' => $cuenta->cuenta_codigo,             'debe' => 0,       'haber' => $ajuste,'glosa_detalle' => "CM Dep. Acum.: {$nombre}"];
            } elseif ($ROL === CmConfiguracionCuenta::ROL_PATRIMONIO_CAPITAL) {
                $totales['patrimonio'] += $ajuste;
                $detalles[] = ['cuenta_contable' => $config->cuenta_patrimonio_codigo,'debe' => $ajuste, 'haber' => 0,      'glosa_detalle' => "CM Resultado Patrimonio"];
                $detalles[] = ['cuenta_contable' => $cuenta->cuenta_codigo,           'debe' => 0,       'haber' => $ajuste,'glosa_detalle' => "CM Patrimonio: {$nombre}"];
            } elseif ($ROL === CmConfiguracionCuenta::ROL_PASIVO_NO_MONETARIO) {
                $totales['pasivos'] += $ajuste;
                $detalles[] = ['cuenta_contable' => $config->cuenta_pasivos_codigo,'debe' => $ajuste, 'haber' => 0,      'glosa_detalle' => "CM Resultado Pasivos"];
                $detalles[] = ['cuenta_contable' => $cuenta->cuenta_codigo,        'debe' => 0,       'haber' => $ajuste,'glosa_detalle' => "CM Pasivo: {$nombre}"];
            }
        }

        $totales['neto'] = $totales['activos'] + $totales['existencias'] + $totales['depreciacion'] + $totales['patrimonio'] + $totales['pasivos'];

        return ['lineas' => $lineas, 'detalles' => $this->consolidarDetalles($detalles), 'totales' => $totales];
    }

    private function getSaldosCuentas(int $empresaId, array $codigos, string $fechaHasta): array
    {
        $filas = DB::table('detalles_asiento as da')
            ->join('asientos_contables as ac', 'da.asiento_id', '=', 'ac.id')
            ->where('ac.empresa_id', $empresaId)
            ->whereIn('da.cuenta_contable', $codigos)
            ->where('ac.estado', 'MAYORIZADO')
            ->where('ac.fecha', '<=', $fechaHasta)
            ->groupBy('da.cuenta_contable')
            ->select('da.cuenta_contable', DB::raw('SUM(da.debe) as d'), DB::raw('SUM(da.haber) as h'))
            ->get();

        $saldos = [];
        foreach ($filas as $fila) {
            $saldos[$fila->cuenta_contable] = (float)$fila->d - (float)$fila->h;
        }

        return $saldos;
    }

    private function consolidarDetalles(array $detalles): array
    {
        $agrupados = [];

        foreach ($detalles as $d) {
            $key = $d['cuenta_contable'];
            if (!isset($agrupados[$key])) {
                $agrupados[$key] = ['cuenta_contable' => $key, 'debe' => 0, 'haber' => 0, 'glosa_detalle' => 'Corrección Monetaria'];
            }
            $agrupados[$key]['debe']  += $d['debe'];
            $agrupados[$key]['haber'] += $d['haber'];
        }

        return array_values($agrupados);
    }

    private function actualizarActivosFijos(int $empresaId, float $factor, int $mes, int $anio): void
    {
        $activos = ActivoFijo::where('empresa_id', $empresaId)->where('estado', 'ACTIVO')->get();

        foreach ($activos as $activo) {
            $activo->update([
                'cm_ajuste_acumulado'              => (float)$activo->cm_ajuste_acumulado + round((float)$activo->valor_adquisicion * ($factor - 1), 2),
                'cm_depreciacion_ajuste_acumulado' => (float)$activo->cm_depreciacion_ajuste_acumulado + round((float)$activo->depreciacion_acumulada * ($factor - 1), 2),
                'ultimo_periodo_cm_mes'            => $mes,
                'ultimo_periodo_cm_anio'           => $anio,
            ]);
        }
    }

    private function recalcularAcumuladosAnio(int $anio): void
    {
        $indices = CmIndiceIpc::where('anio', $anio)->orderBy('mes')->get();
        $factorAcumulado = 1.0;

        foreach ($indices as $indice) {
            $factorAcumulado = round($factorAcumulado * (float)$indice->factor_multiplicador, 6);
            $indice->update(['variacion_acumulada_anual' => round(($factorAcumulado - 1) * 100, 4)]);
        }
    }

    private function nombreMes(int $mes): string
    {
        return match ($mes) {
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',    4 => 'Abril',
            5 => 'Mayo',  6 => 'Junio',   7 => 'Julio',    8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            default => "Mes {$mes}",
        };
    }
}
