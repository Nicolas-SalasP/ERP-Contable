<?php

namespace App\Domains\Rrhh\Services\Lre;

use App\Domains\Core\Models\Empresa;
use App\Domains\Rrhh\DataTransfer\LreData;
use App\Domains\Rrhh\DataTransfer\LreLineaData;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LreEnvio;
use Illuminate\Support\Facades\Storage;

class GenerarLreService
{
    private const AFP_CODIGOS = [
        'Habitat'   => '33',
        'Modelo'    => '34',
        'Capital'   => '29',
        'Cuprum'    => '03',
        'PlanVital' => '35',
        'Provida'   => '05',
        'AFP_UNO'   => '36',
        'IPS'       => '99',
    ];

    private const ISAPRE_CODIGOS = [
        'Banmédica'   => '02',
        'Colmena'     => '05',
        'Cruz Blanca' => '07',
        'Cruz del Sur'=> '08',
        'MasVida'     => '01',
        'Vida Tres'   => '04',
        'Consalud'    => '06',
        'Esencial'    => '13',
    ];

    // ADVERTENCIA (hallazgo pendiente de validar con contador/experto RRHH, no
    // corregido a ciegas): el manual oficial LRE (docs/rrhh-leyes/suplemento_manual_lre.md,
    // campo 1107) define códigos de 3 dígitos -- 101 ORDINARIA, 201 PARCIAL, 601
    // JORNADA EXCEPCIONAL, etc -- que no coinciden con este mapeo (1/2/3). Además
    // 'TURNO' (valor válido de contratos.tipo_jornada) no tiene código aquí y el
    // campo se omite en silencio para esos contratos. Verificar contra la tabla
    // oficial antes de corregir: un código legal incorrecto es peor que omitirlo.
    private const JORNADA_CODIGOS = [
        'COMPLETA'   => 1,
        'PARCIAL'    => 2,
        'EXCEPTUADO' => 3,
    ];

    public function generar(int $empresaId, int $anio, int $mes): LreEnvio
    {
        $liquidaciones = Liquidacion::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('estado', Liquidacion::ESTADO_EMITIDA)
            ->with(['empleado.cargasFamiliares', 'contrato', 'detalles'])
            ->get();

        if ($liquidaciones->isEmpty()) {
            throw RrhhException::regla(
                "No hay liquidaciones emitidas para el período {$mes}/{$anio}."
            );
        }

        // Regenerar sobrescribia el archivo y forzaba estado GENERADO incondicionalmente,
        // incluso si ya estaba CONFIRMADO_DT -- perdiendo la confirmación previa en
        // silencio. Bloquea; si de verdad hay que corregir un LRE ya confirmado, debe
        // hacerse explícitamente (no como efecto colateral de volver a generar).
        $envioExistente = LreEnvio::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->first();

        if ($envioExistente && $envioExistente->estado === LreEnvio::ESTADO_CONFIRMADO_DT) {
            throw RrhhException::regla(
                "El LRE de {$mes}/{$anio} ya fue confirmado ante la Dirección del Trabajo. " .
                "No puede regenerarse; contacte soporte si necesita corregirlo."
            );
        }

        $empresa = Empresa::findOrFail($empresaId);

        $lineas = $liquidaciones->map(fn (Liquidacion $liq) => $this->construirLinea($liq))->all();

        $lreData = new LreData(
            empresaId:            $empresaId,
            rutEmpresa:           $empresa->rut,
            razonSocial:          $empresa->razon_social,
            anio:                 $anio,
            mes:                  $mes,
            cantidadTrabajadores: count($lineas),
            lineas:               $lineas,
        );

        $contenido   = $this->generarContenidoArchivo($lreData);
        $mesFormato  = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
        $rutaArchivo = "lre/{$empresaId}/{$anio}-{$mesFormato}.txt";

        Storage::disk('sii_xml')->put($rutaArchivo, $contenido);

        $envio = LreEnvio::updateOrCreate(
            ['empresa_id' => $empresaId, 'anio' => $anio, 'mes' => $mes],
            [
                'estado'               => LreEnvio::ESTADO_GENERADO,
                'cantidad_trabajadores'=> count($lineas),
                'archivo_path'         => $rutaArchivo,
            ]
        );

        return $envio->fresh();
    }

    private function construirLinea(Liquidacion $liq): LreLineaData
    {
        /** @var \App\Domains\Rrhh\Models\Empleado $empleado */
        $empleado = $liq->empleado;
        /** @var \App\Domains\Rrhh\Models\Contrato $contrato */
        $contrato = $liq->contrato;

        // Fechas contrato
        $fechaInicioContrato  = $contrato->fecha_inicio->format('d/m/Y');
        $fechaTerminoContrato = $contrato->fecha_termino_real?->format('d/m/Y');

        // Causal término: null si el contrato no ha terminado
        $causalTermino = $contrato->fecha_termino_real !== null
            ? ($contrato->causal_termino ? (int) $contrato->causal_termino : null)
            : null;

        // Región: el campo almacena el número de región directamente
        $codigoRegion = $empleado->region ? (int) $empleado->region : null;

        // Jornada
        $codigoJornada = isset(self::JORNADA_CODIGOS[$contrato->tipo_jornada])
            ? self::JORNADA_CODIGOS[$contrato->tipo_jornada]
            : null;

        // Cargas familiares (ya eager-loaded)
        $numCargas = $empleado->cargasFamiliares->count();

        // Días
        $diasTrabajados    = $liq->dias_trabajados ?? 30;
        $diasLicencia      = (int) ($liq->dias_licencia_medica ?? 0);
        $diasVacaciones    = (int) ($liq->dias_vacaciones ?? 0);

        // AFP
        $afpNombre  = $empleado->afp ?? 'Capital';
        $codigoAfp  = self::AFP_CODIGOS[$afpNombre] ?? '99';

        // Salud
        $tipoSalud = $empleado->tipo_salud ?? 'FONASA';
        if ($tipoSalud === 'ISAPRE') {
            $codigoSalud = self::ISAPRE_CODIGOS[$empleado->isapre_nombre ?? ''] ?? '99';
        } else {
            $codigoSalud = '07';
        }

        // AFC según tipo contrato
        $codigoAfc = $contrato->tipo === 'INDEFINIDO' ? '1' : '2';

        // Tramo cargas familiares
        $imponible             = (float) $liq->total_haberes_imponibles;
        $tramoCargasFamiliares = $this->calcularTramoCargasFamiliares($imponible);

        // Detalles indexados por codigo_concepto
        $detalles = $liq->detalles->keyBy('codigo_concepto');

        $sueldo       = (int) ($detalles->get('SUELDO_BASE')->monto ?? 0);
        $sobresueldo  = (int) ($detalles->get('HORAS_EXTRA')->monto ?? 0);
        $gratificacion= (int) ($detalles->get('GRATIFICACION')->monto ?? 0);
        $colacion     = (int) ($detalles->get('COLACION')->monto ?? 0);
        $movilizacion = (int) ($detalles->get('MOVILIZACION')->monto ?? 0);
        $asignFamiliar= (int) ($detalles->get('ASIGNACION_FAMILIAR')->monto ?? 0);

        $otrosHaberesImponibles = (int) $liq->detalles
            ->where('tipo', 'HABER_IMPONIBLE')
            ->whereNotIn('codigo_concepto', ['SUELDO_BASE', 'HORAS_EXTRA', 'GRATIFICACION'])
            ->sum('monto');

        $otrosNoImponibles = (int) $liq->detalles
            ->where('tipo', 'HABER_NO_IMPONIBLE')
            ->whereNotIn('codigo_concepto', ['COLACION', 'MOVILIZACION', 'ASIGNACION_FAMILIAR'])
            ->sum('monto');

        $afpCotizacion = (int) ($detalles->get('AFP_COTIZACION')->monto ?? 0);
        $afpComision   = (int) ($detalles->get('AFP_COMISION')->monto ?? 0);
        $cotizacionAfp = $afpCotizacion + $afpComision;

        $cotizacionSalud           = (int) $liq->salud_legal;
        $cotizacionSaludVoluntaria = (int) $liq->salud_adicional;
        $cotizacionAfc             = (int) ($detalles->get('AFC_TRABAJADOR')->monto ?? 0);
        $impuestoRetenido          = (int) ($detalles->get('IMPUESTO_UNICO')->monto ?? 0);

        $otrosDescuentos = (int) $liq->detalles
            ->where('tipo', 'DESCUENTO_VOLUNTARIO')
            ->sum('monto');

        $aporteAfcEmpleador  = (int) $liq->aporte_empleador_afc;
        $aporteMutual        = (int) $liq->aporte_empleador_mutual;
        $aporteSis           = (int) $liq->aporte_empleador_sis;
        $totalAportesEmpleador = $aporteAfcEmpleador + $aporteMutual + $aporteSis;

        $totalHaberes            = (int) $liq->total_haberes;
        $totalHaberesImponibles  = (int) $liq->total_haberes_imponibles;
        $totalHaberesNoImponibles= (int) $liq->total_haberes_no_imponibles;
        $totalDescuentos         = (int) $liq->total_descuentos;
        $totalDescuentosLegales  = (int) $liq->total_descuentos_legales;
        $liquidoPagar            = (int) $liq->liquido_a_pagar;

        return new LreLineaData(
            rutTrabajador:             $empleado->rut,
            fechaInicioContrato:       $fechaInicioContrato,
            fechaTerminoContrato:      $fechaTerminoContrato,
            causalTermino:             $causalTermino,
            codigoRegion:              $codigoRegion,
            codigoComuna:              $empleado->comuna_codigo_lre ?? null,
            codigoJornada:             $codigoJornada,
            pensionadoInvalidez:       (bool) ($empleado->pensionado_invalidez ?? false),
            pensionadoVejez:           (bool) ($empleado->pensionado_vejez ?? false),
            codigoCcaf:                $empleado->ccaf_codigo ? (int) $empleado->ccaf_codigo : null,
            numCargasFamiliares:       $numCargas,
            numCargasMaternales:       0,
            numCargasInvalidez:        0,
            tramoCargasFamiliares:     $tramoCargasFamiliares,
            diasTrabajados:            $diasTrabajados,
            diasLicenciaMedica:        $diasLicencia,
            diasVacaciones:            $diasVacaciones,
            codigoAfp:                 $codigoAfp,
            codigoSalud:               $codigoSalud,
            codigoAfc:                 $codigoAfc,
            codigoMutual:              $empleado->organismo_mutual_codigo ? (int) $empleado->organismo_mutual_codigo : null,
            sueldo:                    $sueldo,
            sobresueldo:               $sobresueldo,
            gratificacion:             $gratificacion,
            otrosHaberesImponibles:    $otrosHaberesImponibles,
            colacion:                  $colacion,
            movilizacion:              $movilizacion,
            asignacionFamiliar:        $asignFamiliar,
            otrosNoImponibles:         $otrosNoImponibles,
            cotizacionAfp:             $cotizacionAfp,
            cotizacionSalud:           $cotizacionSalud,
            cotizacionSaludVoluntaria: $cotizacionSaludVoluntaria,
            cotizacionAfc:             $cotizacionAfc,
            impuestoRetenido:          $impuestoRetenido,
            otrosDescuentos:           $otrosDescuentos,
            aporteAfcEmpleador:        $aporteAfcEmpleador,
            aporteMutual:              $aporteMutual,
            aporteSis:                 $aporteSis,
            totalHaberes:              $totalHaberes,
            totalHaberesImponibles:    $totalHaberesImponibles,
            totalHaberesNoImponibles:  $totalHaberesNoImponibles,
            totalDescuentos:           $totalDescuentos,
            totalDescuentosLegales:    $totalDescuentosLegales,
            totalAportesEmpleador:     $totalAportesEmpleador,
            liquidoPagar:              $liquidoPagar,
        );
    }

    private function calcularTramoCargasFamiliares(float $imponible): int
    {
        if ($imponible <= 387962) {
            return 1;
        }
        if ($imponible <= 518803) {
            return 2;
        }
        if ($imponible <= 807064) {
            return 3;
        }
        return 4;
    }

    private function generarContenidoArchivo(LreData $data): string
    {
        $mesFormato = str_pad((string) $data->mes, 2, '0', STR_PAD_LEFT);
        $lineas     = [];

        $lineas[] = '## INICIO_LIBRO';
        $lineas[] = "EMPRESA_RUT|{$data->rutEmpresa}";
        $lineas[] = "EMPRESA_RAZON_SOCIAL|{$data->razonSocial}";
        $lineas[] = "PERIODO_ANIO|{$data->anio}";
        $lineas[] = "PERIODO_MES|{$mesFormato}";
        $lineas[] = "CANTIDAD_TRABAJADORES|{$data->cantidadTrabajadores}";

        foreach ($data->lineas as $linea) {
            $lineas[] = '## INICIO_TRABAJADOR';
            $lineas[] = "1101|{$linea->rutTrabajador}";
            $lineas[] = "1102|{$linea->fechaInicioContrato}";

            // Campos opcionales (omitir si vacío)
            if ($linea->fechaTerminoContrato !== null) {
                $lineas[] = "1103|{$linea->fechaTerminoContrato}";
            }
            if ($linea->causalTermino !== null) {
                $lineas[] = "1104|{$linea->causalTermino}";
            }
            if ($linea->codigoRegion !== null) {
                $lineas[] = "1105|{$linea->codigoRegion}";
            }
            if ($linea->codigoComuna !== null) {
                $lineas[] = "1106|{$linea->codigoComuna}";
            }
            if ($linea->codigoJornada !== null) {
                $lineas[] = "1107|{$linea->codigoJornada}";
            }

            $lineas[] = '1108|' . ($linea->pensionadoInvalidez ? '1' : '0');
            $lineas[] = '1109|' . ($linea->pensionadoVejez ? '1' : '0');

            if ($linea->codigoCcaf !== null) {
                $lineas[] = "1110|{$linea->codigoCcaf}";
            }

            $lineas[] = "1111|{$linea->numCargasFamiliares}";
            $lineas[] = "1112|{$linea->numCargasMaternales}";
            $lineas[] = "1113|{$linea->numCargasInvalidez}";
            $lineas[] = "1114|{$linea->tramoCargasFamiliares}";
            $lineas[] = "1115|{$linea->diasTrabajados}";
            $lineas[] = "1116|{$linea->diasLicenciaMedica}";
            $lineas[] = "1117|{$linea->diasVacaciones}";
            $lineas[] = "1141|{$linea->codigoAfp}";
            $lineas[] = "1143|{$linea->codigoSalud}";
            $lineas[] = "1151|{$linea->codigoAfc}";

            if ($linea->codigoMutual !== null) {
                $lineas[] = "1152|{$linea->codigoMutual}";
            }

            // Haberes imponibles
            $lineas[] = "2101|{$linea->sueldo}";
            $lineas[] = "2102|{$linea->sobresueldo}";
            $lineas[] = "2106|{$linea->gratificacion}";
            $lineas[] = "2111|{$linea->otrosHaberesImponibles}";

            // Haberes no imponibles
            $lineas[] = "2301|{$linea->colacion}";
            $lineas[] = "2302|{$linea->movilizacion}";
            $lineas[] = "2311|{$linea->asignacionFamiliar}";
            $lineas[] = "2399|{$linea->otrosNoImponibles}";

            // Descuentos
            $lineas[] = "3141|{$linea->cotizacionAfp}";
            $lineas[] = "3143|{$linea->cotizacionSalud}";
            $lineas[] = "3144|{$linea->cotizacionSaludVoluntaria}";
            $lineas[] = "3151|{$linea->cotizacionAfc}";
            $lineas[] = "3161|{$linea->impuestoRetenido}";
            $lineas[] = "3183|{$linea->otrosDescuentos}";

            // Aportes empleador
            $lineas[] = "4151|{$linea->aporteAfcEmpleador}";
            $lineas[] = "4152|{$linea->aporteMutual}";
            $lineas[] = "4155|{$linea->aporteSis}";

            // Totales
            $lineas[] = "5201|{$linea->totalHaberes}";
            $lineas[] = "5210|{$linea->totalHaberesImponibles}";
            $lineas[] = "5230|{$linea->totalHaberesNoImponibles}";
            $lineas[] = "5301|{$linea->totalDescuentos}";
            $lineas[] = "5341|{$linea->totalDescuentosLegales}";
            $lineas[] = "5410|{$linea->totalAportesEmpleador}";
            $lineas[] = "5501|{$linea->liquidoPagar}";

            $lineas[] = '## FIN_TRABAJADOR';
        }

        $lineas[] = '## FIN_LIBRO';

        return implode("\n", $lineas) . "\n";
    }
}
