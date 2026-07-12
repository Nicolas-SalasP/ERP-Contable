<?php

namespace App\Domains\Rrhh\Services\Previred;

use App\Domains\Core\Models\Empresa;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** R6 — Genera el archivo previsional Previred: texto UTF-8 CRLF, una línea por trabajador, 105 campos separados por ";", sin encabezado, "Archivo de 105 posiciones" (previred.com); verificar campos/códigos contra la doc oficial antes de producción (https://www.previred.com/web/previred/ayuda-planilla); solo se implementan los tipos de movimiento 0=sin movimiento, 1=contratación y 2=retiro, el resto del estándar (licencia médica, permiso, accidente, etc.) no está soportado. */
class PreviredService
{
    // ── Tablas de códigos institucionales ────────────────────────────────────

    private const AFP_CODIGOS = [
        'CAPITAL' => '03',
        'CUPRUM' => '05',
        'HABITAT' => '07',
        'MODELO' => '09',
        'PLANVITAL' => '10',
        'PROVIDA' => '11',
        'UNO' => '13',
    ];

    private const SALUD_CODIGOS = [
        'FONASA' => '07',
        'BANMEDICA' => '01',
        'COLMENA' => '02',
        'CONSALUD' => '03',
        'MASVIDA' => '04',
        'NUEVA MASVIDA' => '06',
        'VIDA TRES' => '08',
        'ESENCIAL' => '52',
    ];

    // Código de régimen previsional AFP (campo 10 del formato 105):
    // 1 = AFP, 2 = IPS (ex-INP), 3 = DIPRECA, 4 = CAPREDENA, 7 = Imponente voluntario
    private const REGIMEN_AFP = '1';

    // Tipo de trabajador (campo 11): 1 = Activo, 2 = Pensionado cotizante
    private const TIPO_TRABAJADOR = '1';

    // Fallback cuando la liquidación no tiene parámetro previsional vinculado.
    // El valor correcto viene de ParametroPrevisional::mutual_codigo (01=ACHS, 02=ISL, 03=Mutual).
    private const MUTUALIDAD_CODIGO_DEFAULT = '01';

    /**
     * Genera el contenido del archivo Previred de 105 campos para el período.
     * Retorna el string listo para descargar; el controller lo envía como respuesta.
     *
     * @throws RrhhException si no hay liquidaciones EMITIDAS o la empresa no existe.
     */
    public function generarArchivo(int $empresaId, int $anio, int $mes): string
    {
        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', 'EMITIDA')
            ->with(['empleado', 'contrato', 'parametro'])
            ->get();

        if ($liquidaciones->isEmpty()) {
            throw RrhhException::regla(
                "No hay liquidaciones EMITIDAS para el período {$mes}/{$anio}. ".
                'Emita las liquidaciones antes de generar el archivo Previred.'
            );
        }

        $empresa = Empresa::find($empresaId);

        // Cargar todos los detalles en una sola consulta y agrupar por liquidacion_id
        $liquidacionIds = $liquidaciones->pluck('id')->all();
        $detallesPorLiq = LiquidacionDetalle::whereIn('liquidacion_id', $liquidacionIds)
            ->select('liquidacion_id', 'codigo_concepto', 'base_calculo', 'tasa_aplicada', 'monto')
            ->get()
            ->groupBy('liquidacion_id');

        $periodo = sprintf('%04d%02d', $anio, $mes);
        $primerDiaMes = Carbon::create($anio, $mes, 1);
        $ultimoDiaMes = $primerDiaMes->copy()->endOfMonth();
        $diasEnMes = (int) $ultimoDiaMes->format('j');

        $lineas = [];
        foreach ($liquidaciones as $liq) {
            $detalles = $detallesPorLiq->get($liq->id, collect());
            $lineas[] = implode(';', $this->construirFila105(
                $liq, $detalles, $periodo, $primerDiaMes, $ultimoDiaMes, $diasEnMes, $empresa
            ));
        }

        return implode("\r\n", $lineas)."\r\n";
    }

    /** Construye el array de 105 campos (1-13 identificación, 14-24 AFP, 25-29 AFC, 30-34 IPS/ISL, 35-51 salud, 52-58 CCAF, 59-69 mutualidad, 70-80 empleador, 81-105 complementarios); campos no poblados por el ERP llevan '' o '0' según convención oficial. */
    private function construirFila105(
        Liquidacion $liq,
        Collection $detalles,
        string $periodo,
        Carbon $primerDiaMes,
        Carbon $ultimoDiaMes,
        int $diasEnMes,
        ?Empresa $empresa
    ): array {
        /** @var Empleado $empleado */
        $empleado = $liq->empleado;
        /** @var Contrato|null $contrato */
        $contrato = $liq->contrato;

        // ── Identificación trabajador ─────────────────────────────────────────
        [$rut, $dv] = $this->separarRut($empleado->rut ?? '');
        $apPaterno = strtoupper($empleado->apellido_paterno ?? '');
        $apMaterno = strtoupper($empleado->apellido_materno ?? '');
        $nombres = strtoupper($empleado->nombres ?? '');
        // campo 6: M/F; 'O' (otro) queda vacío porque Previred no tiene ese código
        $sexo = in_array($empleado->sexo, ['M', 'F'], true) ? $empleado->sexo : '';
        // campo 7: código ISO 3166-1 alpha-3 (ej. CHL, ARG, PER); default CHL
        $nacionalidad = strtoupper($empleado->nacionalidad ?? 'CHL');
        $tipoPago = '3'; // campo 8: 3=transferencia (default razonable; sin dato específico)

        // Período desde/hasta (campos 9 y 10): AAAAMM del período de trabajo
        $periodoDesde = $periodo;
        $periodoHasta = $periodo;

        // Campo 10: régimen previsional, campo 11: tipo trabajador, campo 12: días trabajados
        $regimenPrevisional = self::REGIMEN_AFP;
        $tipoTrabajador = self::TIPO_TRABAJADOR;
        $diasTrabajados = $this->calcularDiasTrabajados($contrato, $primerDiaMes, $ultimoDiaMes, $diasEnMes);

        // Campo 13: tipo de línea — 0 = normal
        $tipoLinea = '0';

        // Tipo movimiento (campo 6 lógico, posición real en bloque):
        // determinado por fecha de inicio/término del contrato dentro del mes
        $tipoMovimiento = $this->calcularTipoMovimiento($contrato, $primerDiaMes, $ultimoDiaMes);

        // ── Montos desde detalles de liquidación ─────────────────────────────
        $afpBase = $this->baseCalculo($detalles, ConceptoRemuneracion::AFP_COTIZACION);
        $afpCot = $this->montoConcepto($detalles, ConceptoRemuneracion::AFP_COTIZACION);
        $afpCom = $this->montoConcepto($detalles, ConceptoRemuneracion::AFP_COMISION);

        $saludBase = $this->baseCalculo($detalles, ConceptoRemuneracion::SALUD);
        $saludCot = $this->montoConcepto($detalles, ConceptoRemuneracion::SALUD);

        $afcBase = $this->baseCalculo($detalles, ConceptoRemuneracion::AFC_TRABAJADOR);
        $afcTrab = $this->montoConcepto($detalles, ConceptoRemuneracion::AFC_TRABAJADOR);

        // Aportes empleador desde la cabecera de la liquidación
        $afcEmp = (float) ($liq->aporte_empleador_afc ?? 0);
        $sisMonto = (float) ($liq->aporte_empleador_sis ?? 0);
        $mutualMonto = (float) ($liq->aporte_empleador_mutual ?? 0);

        $iuscBase = (float) ($liq->base_tributable ?? 0);
        $iuscMonto = $this->montoConcepto($detalles, ConceptoRemuneracion::IMPUESTO_UNICO);
        $liquido = (float) ($liq->liquido_a_pagar ?? 0);

        // Tipo de contrato AFC: 1 = indefinido, 2 = plazo fijo
        $tipoAfc = ($contrato && $contrato->tipo === 'INDEFINIDO') ? '1' : '2';

        // Códigos institucionales
        $afpCodigo = $this->codigoAfp($empleado->afp ?? '');
        $saludCodigo = $this->codigoSalud($empleado->tipo_salud ?? '', $empleado->isapre_nombre ?? '');

        // Cotización adicional ISAPRE: max(0, plan_pesos − 7% imponible); 0 si es FONASA o no hay plan UF.
        $cotAdicionalIsapre = $this->calcularAdicionalIsapre(
            $empleado,
            $saludBase,
            $liq
        );
        // saludCot (7% obligatorio, calculado en LiquidacionService) y el adicional ISAPRE son campos distintos informados por separado.

        $mutualCodigo = $this->resolverCodigoMutualPrevired($empresa, $liq);

        // Datos empleador (RUT separado)
        [$rutEmp, $dvEmp] = $empresa ? $this->separarRut($empresa->rut) : ['', ''];

        // ── Ensamblado de los 105 campos: no disponibles en el ERP se marcan con '' o '0' según tipo (ver FORMATO-PREVIRED.md para lista completa) ──

        return [
            // === BLOQUE 1: Identificación trabajador (campos 1-13) ===
            /* 01 */ $rut,                        // RUT trabajador (sin DV, sin puntos)
            /* 02 */ $dv,                         // Dígito verificador
            /* 03 */ $apPaterno,                  // Apellido paterno (mayúsculas)
            /* 04 */ $apMaterno,                  // Apellido materno (mayúsculas)
            /* 05 */ $nombres,                    // Nombres (mayúsculas)
            /* 06 */ $sexo,                       // Sexo: M/F (vacío si no aplica)
            /* 07 */ $nacionalidad,               // Nacionalidad código ISO alpha-3
            /* 08 */ $tipoPago,                   // Tipo de pago: 3=transferencia
            /* 09 */ $periodoDesde,               // Período desde (AAAAMM)
            /* 10 */ $periodoHasta,               // Período hasta (AAAAMM)
            /* 11 */ $regimenPrevisional,         // Régimen previsional: 1=AFP
            /* 12 */ $tipoTrabajador,             // Tipo trabajador: 1=activo
            /* 13 */ (string) $diasTrabajados,    // Días trabajados en el período

            // === BLOQUE 2: AFP (campos 14-24) ===
            /* 14 */ $tipoMovimiento,             // Tipo de movimiento (0,1,2...)
            /* 15 */ $afpCodigo,                  // Código AFP Previred
            /* 16 */ '',                          // CUSPP (AFP extranjera) — vacío Chile
            /* 17 */ $this->pesos($afpBase),      // Renta imponible AFP
            /* 18 */ $this->pesos($afpCot),       // Cotización obligatoria AFP (10%)
            /* 19 */ $this->pesos($sisMonto),     // Cotización SIS (aporte empleador)
            /* 20 */ $this->pesos($afpCom),       // Comisión AFP
            /* 21 */ '0',                         // Cotización voluntaria AFP — sin dato ERP
            /* 22 */ '0',                         // APV (Ahorro Previsional Voluntario) — sin dato
            /* 23 */ '0',                         // APV colectivo trabajador — sin dato
            /* 24 */ '0',                         // APV colectivo empleador — sin dato

            // === BLOQUE 3: Seguro de Cesantía AFC (campos 25-29) ===
            /* 25 */ $tipoAfc,                    // Tipo AFC: 1=indefinido, 2=plazo fijo
            /* 26 */ $this->pesos($afcBase),      // Renta imponible AFC
            /* 27 */ $this->pesos($afcTrab),      // Cotización AFC trabajador
            /* 28 */ $this->pesos($afcEmp),       // Cotización AFC empleador
            /* 29 */ '0',                         // Aporte retiro AFC empleador — sin dato ERP

            // === BLOQUE 4: IPS / ex-INP (campos 30-34) ===
            /* 30 */ '',                          // Código caja IPS — sin dato ERP (empleados AFP)
            /* 31 */ '0',                         // Renta imponible IPS — sin dato ERP
            /* 32 */ '0',                         // Cotización IPS — sin dato ERP
            /* 33 */ '0',                         // Cotización adicional IPS — sin dato ERP
            /* 34 */ '0',                         // Cotización especial IPS — sin dato ERP

            // === BLOQUE 5: Salud (campos 35-51) ===
            /* 35 */ $saludCodigo,                // Código institución salud (07=Fonasa)
            /* 36 */ '',                          // Número FUN ISAPRE — sin dato ERP
            /* 37 */ $this->pesos($saludBase),    // Renta imponible salud
            /* 38 */ $this->pesos($saludCot),     // Cotización obligatoria 7%
            /* 39 */ $this->pesos($cotAdicionalIsapre), // Cotización adicional ISAPRE
            /* 40 */ '0',                         // Cotización ISAPRE GES — sin dato ERP
            /* 41 */ '0',                         // Cotización ISAPRE catastrófico — sin dato ERP
            /* 42 */ '0',                         // Subsidio incapacidad laboral — sin dato ERP
            /* 43 */ '0',                         // Otros descuentos salud — sin dato ERP
            /* 44 */ '',                          // Número credencial salud — sin dato ERP
            /* 45 */ '',                          // Tipo cobertura — sin dato ERP
            /* 46 */ '0',                         // Meses cotizados salud — sin dato ERP
            /* 47 */ '0',                         // Cargas simples — sin dato ERP
            /* 48 */ '0',                         // Cargas dobles — sin dato ERP
            /* 49 */ '0',                         // Cargas maternales — sin dato ERP
            /* 50 */ '0',                         // Cargas inválidas — sin dato ERP
            /* 51 */ '0',                         // Cotización salud empleador — sin dato ERP

            // === BLOQUE 6: CCAF (campos 52-58) ===
            /* 52 */ '',                          // Código CCAF — sin dato ERP
            /* 53 */ '0',                         // Cargas CCAF — sin dato ERP
            /* 54 */ '0',                         // Asignación familiar CCAF — sin dato ERP
            /* 55 */ '0',                         // Subsidio CCAF — sin dato ERP
            /* 56 */ '0',                         // Crédito social CCAF — sin dato ERP
            /* 57 */ '0',                         // Otros descuentos CCAF — sin dato ERP
            /* 58 */ '0',                         // Cotización CCAF — sin dato ERP

            // === BLOQUE 7: Mutualidad / Seguro Accidentes del Trabajo (campos 59-69) ===
            /* 59 */ $mutualCodigo,               // Código mutualidad (01=ACHS, 02=ISL, 03=Mutual)
            /* 60 */ $this->pesos($mutualMonto),  // Cotización mutualidad básica (0,9%)
            /* 61 */ '0',                         // Cotización mutualidad adicional — sin dato ERP
            /* 62 */ '0',                         // Cotización diferenciada mutualidad — sin dato ERP
            /* 63 */ '0',                         // Cotización extraordinaria mutualidad — sin dato ERP
            /* 64 */ '0',                         // Días subsidio accidente laboral — sin dato ERP
            /* 65 */ '0',                         // Monto subsidio accidente laboral — sin dato ERP
            /* 66 */ '0',                         // Días suspensión mutualidad — sin dato ERP
            /* 67 */ '0',                         // Tasa cotización diferenciada — sin dato ERP
            /* 68 */ '0',                         // Renta imponible mutualidad — sin dato ERP
            /* 69 */ '0',                         // Tipo accidente — sin dato ERP

            // === BLOQUE 8: Datos empleador (campos 70-80) ===
            /* 70 */ $rutEmp,                     // RUT empleador (sin DV, sin puntos)
            /* 71 */ $dvEmp,                      // DV empleador
            /* 72 */ '',                          // Código actividad económica — sin dato ERP
            /* 73 */ '',                          // Dirección empleador — sin dato ERP
            /* 74 */ '',                          // Ciudad empleador — sin dato ERP
            /* 75 */ '',                          // Teléfono empleador — sin dato ERP
            /* 76 */ '',                          // Email empleador — sin dato ERP
            /* 77 */ '',                          // Nombre representante legal — sin dato ERP
            /* 78 */ '',                          // RUT representante legal — sin dato ERP
            /* 79 */ '',                          // DV representante legal — sin dato ERP
            /* 80 */ '',                          // Cargo representante legal — sin dato ERP

            // === BLOQUE 9: Tributario / Otros (campos 81-95) ===
            /* 81 */ $this->pesos($iuscBase),     // Base tributable IUSC (2ª categoría)
            /* 82 */ $this->pesos($iuscMonto),    // Impuesto único retenido
            /* 83 */ $this->pesos($liquido),      // Líquido a pagar
            /* 84 */ '0',                         // Asignación familiar directa — sin dato ERP
            /* 85 */ '0',                         // Número de cargas directas — sin dato ERP
            /* 86 */ '0',                         // Descuentos voluntarios — sin dato ERP
            /* 87 */ '0',                         // Préstamo institucional — sin dato ERP
            /* 88 */ '0',                         // Descuento cuota sindical — sin dato ERP
            /* 89 */ '0',                         // Otros descuentos — sin dato ERP
            /* 90 */ '0',                         // Anticipo sueldo — sin dato ERP
            /* 91 */ '0',                         // Sueldo base — sin dato ERP (no requerido)
            /* 92 */ '0',                         // Bono empresa — sin dato ERP
            /* 93 */ '0',                         // Horas extras — sin dato ERP
            /* 94 */ '0',                         // Total haberes — sin dato ERP
            /* 95 */ '0',                         // Total descuentos — sin dato ERP

            // === BLOQUE 10: Reservados / Complementarios (campos 96-105) ===
            /* 96 */ '',
            /* 97 */ '',
            /* 98 */ '',
            /* 99 */ '',
            /* 100 */ '',
            /* 101 */ '',
            /* 102 */ '',
            /* 103 */ '',
            /* 104 */ '',
            /* 105 */ '',
        ];
    }

    // ── Lógica de negocio ─────────────────────────────────────────────────────

    /** Días trabajados en el período: recorta al rango [inicio, término] del contrato si cae dentro del mes, o el mes completo (28-31 días) en caso contrario. */
    private function calcularDiasTrabajados(
        ?Contrato $contrato,
        Carbon $primerDiaMes,
        Carbon $ultimoDiaMes,
        int $diasEnMes
    ): int {
        if (! $contrato) {
            return $diasEnMes;
        }

        $inicio = Carbon::instance($contrato->fecha_inicio);
        $termino = $contrato->fecha_termino ? Carbon::instance($contrato->fecha_termino) : null;

        $desde = $primerDiaMes->copy();
        $hasta = $ultimoDiaMes->copy();

        // Contrato inicia dentro del mes
        if ($inicio->between($primerDiaMes, $ultimoDiaMes)) {
            $desde = $inicio->copy();
        }

        // Contrato termina dentro del mes
        if ($termino && $termino->between($primerDiaMes, $ultimoDiaMes)) {
            $hasta = $termino->copy();
        }

        $dias = (int) $desde->diffInDays($hasta) + 1;

        return max(1, min($dias, $diasEnMes));
    }

    /** Tipo de movimiento: 0 = sin movimiento (mes completo), 1 = contratación (inicio en el período), 2 = retiro (término en el período). */
    private function calcularTipoMovimiento(
        ?Contrato $contrato,
        Carbon $primerDiaMes,
        Carbon $ultimoDiaMes
    ): string {
        if (! $contrato) {
            return '0';
        }

        $inicio = Carbon::instance($contrato->fecha_inicio);
        $termino = $contrato->fecha_termino ? Carbon::instance($contrato->fecha_termino) : null;

        if ($inicio->between($primerDiaMes, $ultimoDiaMes)) {
            return '1';
        }

        if ($termino && $termino->between($primerDiaMes, $ultimoDiaMes)) {
            return '2';
        }

        return '0';
    }

    /** Cotización adicional ISAPRE = max(0, plan_pesos − cotización_obligatoria_7%); retorna 0 si es FONASA o sin plan UF; la UF se obtiene del indicador mensual de la liquidación. */
    private function calcularAdicionalIsapre(
        Empleado $empleado,
        float $saludBase,
        Liquidacion $liq
    ): float {
        if (strtoupper($empleado->tipo_salud ?? '') !== 'ISAPRE') {
            return 0.0;
        }

        $planUf = (float) ($empleado->isapre_plan_uf ?? 0);
        if ($planUf <= 0) {
            return 0.0;
        }

        // UF del período (indicador mensual cargado en la liquidación)
        $ufValor = 0.0;
        if ($liq->indicador_mensual_id) {
            $indicador = $liq->indicador;
            $ufValor = (float) ($indicador->uf_valor ?? 0);
        }

        if ($ufValor <= 0) {
            return 0.0;
        }

        $planPesos = $planUf * $ufValor;
        $cotizacionObligat = $saludBase * 0.07;

        return max(0.0, $planPesos - $cotizacionObligat);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function separarRut(string $rut): array
    {
        // RUT almacenado como "12.345.678-9" → ['12345678', '9']
        $limpio = strtoupper(str_replace(['.', ' '], '', $rut));
        if (str_contains($limpio, '-')) {
            [$numero, $dv] = explode('-', $limpio, 2);
        } else {
            $dv = substr($limpio, -1);
            $numero = substr($limpio, 0, -1);
        }

        return [preg_replace('/\D/', '', $numero), $dv];
    }

    private function montoConcepto(Collection $detalles, string $codigo): float
    {
        return (float) ($detalles->firstWhere('codigo_concepto', $codigo)->monto ?? 0);
    }

    private function baseCalculo(Collection $detalles, string $codigo): float
    {
        return (float) ($detalles->firstWhere('codigo_concepto', $codigo)->base_calculo ?? 0);
    }

    private function pesos(float $monto): string
    {
        return (string) (int) round($monto);
    }

    /**
     * Resuelve el código de mutualidad (campo 59). Distingue explícitamente "empresa sin
     * mutualidad asignada" (cae al parámetro previsional legacy, empresa aún no migrada) de
     * "empresa con mutualidad asignada pero sin código Previred oficial confirmado" (IST/Mutual
     * CChC, ver migración 2026_07_10_000001) — este segundo caso NO debe caer silenciosamente al
     * parámetro legacy/global, porque ese valor no representa la elección real de la empresa.
     */
    private function resolverCodigoMutualPrevired(?Empresa $empresa, Liquidacion $liq): string
    {
        $mutualidad = $empresa?->mutualidad;

        if ($mutualidad === null) {
            return $liq->parametro->mutual_codigo ?? self::MUTUALIDAD_CODIGO_DEFAULT;
        }

        if ($mutualidad->codigo_previred !== null) {
            return $mutualidad->codigo_previred;
        }

        Log::warning('PreviredService: empresa con mutualidad sin codigo_previred confirmado, se usa default aproximado', [
            'empresa_id' => $empresa->id,
            'mutualidad_id' => $mutualidad->id,
            'mutualidad_nombre' => $mutualidad->nombre,
        ]);

        return self::MUTUALIDAD_CODIGO_DEFAULT;
    }

    private function codigoAfp(string $afp): string
    {
        $clave = strtoupper(trim($afp));

        return self::AFP_CODIGOS[$clave] ?? '00';
    }

    private function codigoSalud(string $tipoSalud, string $isapreNombre): string
    {
        if (strtoupper($tipoSalud) === 'FONASA') {
            return '07';
        }
        $clave = strtoupper(trim($isapreNombre));

        return self::SALUD_CODIGOS[$clave] ?? '00';
    }
}
