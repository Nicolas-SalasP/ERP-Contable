<?php

namespace App\Domains\Rrhh\Services\Previred;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\ConceptoRemuneracion;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * R6 — Generación del archivo previsional mensual para Previred.
 *
 * Produce un CSV semicolon-delimitado, UTF-8, con una fila por trabajador
 * que haya tenido liquidación EMITIDA en el período. El formato sigue la
 * especificación de "Carga de Planilla" de Previred (previred.com).
 *
 * IMPORTANTE: Verificar los campos y códigos de institución contra la
 * documentación oficial de Previred antes de envío a producción. Los
 * códigos AFP e ISAPRE y las posiciones de columna pueden cambiar.
 * Fuente de referencia: https://www.previred.com/web/previred/ayuda-planilla
 *
 * Codificación instituciones de salud (código Previred):
 *   FONASA     = 07
 *   Banmédica  = 01
 *   Colmena    = 02
 *   Consalud   = 03
 *   MásVida    = 04
 *   Nueva MásVida = 06
 *   Vida Tres  = 08
 *   Esencial   = 52
 *
 * Codificación AFP (código Previred):
 *   Capital    = 03
 *   Cuprum     = 05
 *   Habitat    = 07
 *   Modelo     = 09
 *   PlanVital  = 10
 *   ProVida    = 11
 *   Uno        = 13
 */
class PreviredService
{
    // ── Tablas de códigos institucionales ────────────────────────────────────

    private const AFP_CODIGOS = [
        'CAPITAL'   => '03',
        'CUPRUM'    => '05',
        'HABITAT'   => '07',
        'MODELO'    => '09',
        'PLANVITAL' => '10',
        'PROVIDA'   => '11',
        'UNO'       => '13',
    ];

    private const SALUD_CODIGOS = [
        'FONASA'        => '07',
        'BANMEDICA'     => '01',
        'COLMENA'       => '02',
        'CONSALUD'      => '03',
        'MASVIDA'       => '04',
        'NUEVA MASVIDA' => '06',
        'VIDA TRES'     => '08',
        'ESENCIAL'      => '52',
    ];

    /**
     * Genera el contenido del archivo CSV para el período.
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
            ->with(['empleado', 'contrato'])
            ->get();

        if ($liquidaciones->isEmpty()) {
            throw RrhhException::regla(
                "No hay liquidaciones EMITIDAS para el período {$mes}/{$anio}. " .
                "Emita las liquidaciones antes de generar el archivo Previred."
            );
        }

        // Cargar todos los detalles en una sola consulta y agrupar por liquidacion_id
        $liquidacionIds = $liquidaciones->pluck('id')->all();
        $detallesPorLiq = LiquidacionDetalle::whereIn('liquidacion_id', $liquidacionIds)
            ->select('liquidacion_id', 'codigo_concepto', 'base_calculo', 'tasa_aplicada', 'monto')
            ->get()
            ->groupBy('liquidacion_id');

        $periodo = sprintf('%04d%02d', $anio, $mes);
        $filas   = [];

        foreach ($liquidaciones as $liq) {
            $detalles = $detallesPorLiq->get($liq->id, collect());
            $filas[] = $this->construirFila($liq, $detalles, $periodo);
        }

        return $this->ensamblarCsv($filas, $anio, $mes);
    }

    /**
     * Construye la fila de un trabajador en el formato Previred.
     */
    private function construirFila(Liquidacion $liq, Collection $detalles, string $periodo): array
    {
        $empleado = $liq->empleado;
        $contrato = $liq->contrato;

        // Separar RUT y dígito verificador
        [$rut, $dv] = $this->separarRut($empleado->rut);

        // Montos de componentes individuales desde los detalles
        $afpBase    = $this->baseCalculo($detalles, ConceptoRemuneracion::AFP_COTIZACION);
        $afpCot     = $this->montoConcepto($detalles, ConceptoRemuneracion::AFP_COTIZACION);
        $afpCom     = $this->montoConcepto($detalles, ConceptoRemuneracion::AFP_COMISION);

        $saludBase  = $this->baseCalculo($detalles, ConceptoRemuneracion::SALUD);
        $saludCot   = $this->montoConcepto($detalles, ConceptoRemuneracion::SALUD);

        $afcBase    = $this->baseCalculo($detalles, ConceptoRemuneracion::AFC_TRABAJADOR);
        $afcTrab    = $this->montoConcepto($detalles, ConceptoRemuneracion::AFC_TRABAJADOR);

        // Aportes empleador vienen de la cabecera de la liquidación
        $afcEmp     = (float) ($liq->aporte_empleador_afc ?? 0);
        $sisMonto   = (float) ($liq->aporte_empleador_sis ?? 0);
        $mutualMonto = (float) ($liq->aporte_empleador_mutual ?? 0);

        $iuscBase   = (float) ($liq->base_tributable ?? 0);
        $iuscMonto  = $this->montoConcepto($detalles, ConceptoRemuneracion::IMPUESTO_UNICO);

        $liquido    = (float) ($liq->liquido_a_pagar ?? 0);

        // Tipo de contrato AFC
        $tipoAfc = ($contrato && $contrato->tipo === 'INDEFINIDO') ? '1' : '2';

        // Código AFP e institución de salud
        $afpCodigo   = $this->codigoAfp($empleado->afp ?? '');
        $saludCodigo = $this->codigoSalud($empleado->tipo_salud ?? '', $empleado->isapre_nombre ?? '');

        // Nombre completo
        $nombres       = strtoupper($empleado->nombres ?? '');
        $apPaterno     = strtoupper($empleado->apellido_paterno ?? '');
        $apMaterno     = strtoupper($empleado->apellido_materno ?? '');

        return [
            $rut,                       // 01 RUT trabajador (sin DV)
            $dv,                        // 02 DV
            $apPaterno,                 // 03 Apellido paterno
            $apMaterno,                 // 04 Apellido materno
            $nombres,                   // 05 Nombres
            'A',                        // 06 Tipo movimiento: A=activo
            $periodo,                   // 07 Período (AAAAMM)
            '30',                       // 08 Días cotizados (mes completo)
            $afpCodigo,                 // 09 Código AFP Previred
            $this->pesos($afpBase),     // 10 Remuneración imponible AFP (pesos)
            $this->pesos($afpCot),      // 11 Cotización obligatoria AFP (10%)
            $this->pesos($afpCom),      // 12 Comisión AFP
            $saludCodigo,               // 13 Código institución salud
            $this->pesos($saludBase),   // 14 Remuneración imponible salud
            $this->pesos($saludCot),    // 15 Cotización salud (7% o plan UF)
            '0',                        // 16 Cotización adicional ISAPRE (incluida en saludCot)
            $tipoAfc,                   // 17 Tipo AFC: 1=indefinido 2=plazo fijo
            $this->pesos($afcBase),     // 18 Remuneración imponible AFC
            $this->pesos($afcTrab),     // 19 Cotización AFC trabajador
            $this->pesos($afcEmp),      // 20 Cotización AFC empleador
            $this->pesos($sisMonto),    // 21 Cotización SIS (empleador)
            $this->pesos($mutualMonto), // 22 Cotización mutual accidentes (empleador)
            $this->pesos($iuscBase),    // 23 Base tributable IUSC
            $this->pesos($iuscMonto),   // 24 Impuesto único 2ª cat.
            $this->pesos($liquido),     // 25 Líquido a pagar
        ];
    }

    /**
     * Ensambla el CSV con encabezado descriptivo.
     */
    private function ensamblarCsv(array $filas, int $anio, int $mes): string
    {
        $header = [
            'RUT', 'DV', 'AP_PATERNO', 'AP_MATERNO', 'NOMBRES',
            'TIPO_MOVIMIENTO', 'PERIODO', 'DIAS_COTIZADOS',
            'AFP_CODIGO', 'RIM_AFP', 'COTIZACION_AFP', 'COMISION_AFP',
            'SALUD_CODIGO', 'RIM_SALUD', 'COTIZACION_SALUD', 'COTIZACION_ADICIONAL_ISAPRE',
            'TIPO_AFC', 'RIM_AFC', 'COTIZACION_AFC_TRABAJADOR', 'COTIZACION_AFC_EMPLEADOR',
            'COTIZACION_SIS', 'COTIZACION_MUTUAL',
            'BASE_TRIBUTABLE', 'IMPUESTO_UNICO',
            'LIQUIDO_PAGAR',
        ];

        $lines = [];
        $lines[] = implode(';', $header);
        foreach ($filas as $fila) {
            $lines[] = implode(';', $fila);
        }

        return implode("\r\n", $lines) . "\r\n";
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
        return (float) ($detalles->firstWhere('codigo_concepto', $codigo)?->monto ?? 0);
    }

    private function baseCalculo(Collection $detalles, string $codigo): float
    {
        return (float) ($detalles->firstWhere('codigo_concepto', $codigo)?->base_calculo ?? 0);
    }

    private function pesos(float $monto): string
    {
        return (string) (int) round($monto);
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
