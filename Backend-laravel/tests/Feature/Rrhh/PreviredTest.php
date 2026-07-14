<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Core\Models\Rol;
use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\IndicadorMensual;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\Mutualidad;
use App\Domains\Rrhh\Models\ParametroPrevisional;
use App\Domains\Rrhh\Models\TablaImpuestoUnico;
use App\Domains\Rrhh\Services\Calculo\LiquidacionService;
use App\Domains\Rrhh\Services\Previred\PreviredService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R6 — Generación del archivo previsional para Previred (formato 105 campos).
 *
 * Valida:
 * - Estructura posicional: 105 campos por fila, sin encabezado.
 * - Tipo de movimiento: 0 (normal), 1 (contratación), 2 (retiro).
 * - Días trabajados proporcionales cuando contrato inicia/termina en el mes.
 * - Cotización adicional ISAPRE.
 * - Códigos AFP y salud correctos.
 * - Aislamiento multitenant.
 */
class PreviredTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    // ── Posiciones de columna en el archivo de 105 campos (0-indexado) ────────
    // Bloque 1: identificación trabajador
    private const COL_RUT = 0;

    private const COL_DV = 1;

    private const COL_AP_PATERNO = 2;

    private const COL_AP_MATERNO = 3;

    private const COL_NOMBRES = 4;

    private const COL_SEXO = 5;

    private const COL_NACIONALIDAD = 6;

    private const COL_TIPO_PAGO = 7;

    private const COL_PERIODO_DESDE = 8;

    private const COL_PERIODO_HASTA = 9;

    private const COL_REGIMEN = 10;

    private const COL_TIPO_TRABAJADOR = 11;

    private const COL_DIAS_TRABAJADOS = 12;

    // Bloque 2: AFP
    private const COL_TIPO_MOV = 13;

    private const COL_AFP_CODIGO = 14;

    private const COL_CUSPP = 15;

    private const COL_RIM_AFP = 16;

    private const COL_COT_AFP = 17;

    private const COL_SIS = 18;

    private const COL_COM_AFP = 19;

    // Bloque 3: AFC
    private const COL_TIPO_AFC = 24;

    private const COL_RIM_AFC = 25;

    private const COL_AFC_TRAB = 26;

    private const COL_AFC_EMP = 27;

    // Bloque 5: Salud
    private const COL_SALUD_CODIGO = 34;

    private const COL_FUN = 35;

    private const COL_RIM_SALUD = 36;

    private const COL_COT_SALUD = 37;

    private const COL_COT_ADIC_ISAPRE = 38;

    // Bloque 8: empleador
    private const COL_RUT_EMP = 69;

    private const COL_DV_EMP = 70;

    // Bloque 9: tributario/otros
    private const COL_BASE_TRIB = 80;

    private const COL_IUSC = 81;

    private const COL_LIQUIDO = 82;

    private const TOTAL_CAMPOS = 105;

    private PreviredService $service;

    private LiquidacionService $liquidacionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(PreviredService::class);
        $this->liquidacionService = app(LiquidacionService::class);
        $this->cargarParametrosLegales();
    }

    private function cargarParametrosLegales(): void
    {
        ParametroPrevisional::create([
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
            'afp_cotizacion_pct' => 10.0,
            'afp_comisiones_json' => ['Habitat' => 1.27, 'Modelo' => 0.58, 'Capital' => 1.44, 'Uno' => 0.46],
            'afp_sis_pct' => 1.62,
            'tope_imponible_uf' => 90.0,
            'salud_fonasa_pct' => 7.0,
            'afc_indefinido_trabajador_pct' => 0.6,
            'afc_indefinido_empleador_pct' => 2.4,
            'afc_plazo_fijo_trabajador_pct' => 0.0,
            'afc_plazo_fijo_empleador_pct' => 3.0,
            'afc_tope_imponible_uf' => 135.2,
            'imm' => 539000,
            'gratificacion_tope_mensual_factor' => 4.75,
            'cotizacion_adicional_empleador_pct' => 1.0,
            'mutual_cotizacion_basica_pct' => 0.9,
            'asignacion_familiar_tramos_json' => [
                ['hasta_pesos' => 620251, 'monto_por_carga' => 22007],
                ['hasta_pesos' => null,   'monto_por_carga' => 0],
            ],
            'fuente' => 'test',
        ]);

        IndicadorMensual::create([
            'anio' => 2026,
            'mes' => 6,
            'uf_valor' => 39850,
            'utm_valor' => 71506,
            'uta_valor' => 71506 * 12,
        ]);

        foreach ([
            [1, 0.0,   13.5,  0.0,   0.0],
            [2, 13.5,  30.0,  0.04,  0.54],
            [3, 30.0,  50.0,  0.08,  1.74],
            [4, 50.0,  70.0,  0.135, 4.49],
            [5, 70.0,  90.0,  0.23,  11.14],
            [6, 90.0,  120.0, 0.304, 17.80],
            [7, 120.0, 310.0, 0.35,  23.80],
            [8, 310.0, null,  0.40,  39.30],
        ] as [$o, $d, $h, $t, $f]) {
            TablaImpuestoUnico::create([
                'anio' => 2026,
                'orden' => $o,
                'desde_utm' => $d,
                'hasta_utm' => $h,
                'tasa' => $t,
                'factor_deduccion_utm' => $f,
            ]);
        }
    }

    private function crearEmpleadoConContrato(
        int $empresaId,
        string $afp = 'Habitat',
        string $tipoSalud = 'FONASA',
        string $tipoContrato = 'INDEFINIDO',
        float $sueldo = 900000,
        string $fechaInicio = '2024-01-01',
        ?string $fechaTermino = null,
        ?string $isapreNombre = null,
        float $isapre_plan_uf = 0.0
    ): Empleado {
        $rut = '12.'.rand(100, 999).'.'.rand(100, 999).'-'.rand(0, 9);
        $empleado = Empleado::create([
            'empresa_id' => $empresaId,
            'rut' => $rut,
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'González',
            'apellido_materno' => 'Pérez',
            'afp' => $afp,
            'tipo_salud' => $tipoSalud,
            'isapre_nombre' => $isapreNombre,
            'isapre_plan_uf' => $isapre_plan_uf,
        ]);

        Contrato::create([
            'empresa_id' => $empresaId,
            'empleado_id' => $empleado->id,
            'tipo' => $tipoContrato,
            'fecha_inicio' => $fechaInicio,
            'fecha_termino' => $fechaTermino,
            'sueldo_base' => $sueldo,
            'horas_semana' => 42,
            'estado' => $fechaTermino ? 'TERMINADO' : 'VIGENTE',
            'es_contrato_activo' => $fechaTermino ? false : true,
        ]);

        return $empleado->fresh();
    }

    private function calcularYEmitir(int $empresaId, int $empleadoId): Liquidacion
    {
        $liq = $this->liquidacionService->calcular($empresaId, $empleadoId, 2026, 6);

        return $this->liquidacionService->emitir($empresaId, $liq->id);
    }

    /** Parsea una línea del archivo → array de 105 campos */
    private function parsearLinea(string $linea): array
    {
        return explode(';', $linea);
    }

    /** Devuelve las líneas de datos del archivo (sin filtrar encabezado porque no hay) */
    private function lineasArchivo(string $contenido): array
    {
        return array_values(array_filter(explode("\r\n", $contenido)));
    }

    // ── Tests estructura ───────────────────────────────────────────────────────

    public function test_cada_fila_tiene_exactamente_105_campos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = $this->lineasArchivo($contenido);

        $this->assertCount(1, $lineas, 'Debe haber exactamente 1 fila de datos');
        $campos = $this->parsearLinea($lineas[0]);
        $this->assertCount(self::TOTAL_CAMPOS, $campos,
            'Cada fila debe tener 105 campos; encontrados: '.count($campos));
    }

    public function test_no_hay_linea_de_encabezado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = $this->lineasArchivo($contenido);
        $primeraLinea = $lineas[0];

        // Si la primera línea fuera encabezado contendría texto en campo 1
        $campos = $this->parsearLinea($primeraLinea);
        // El campo 1 (índice 0) siempre es el RUT numérico del trabajador
        $this->assertIsNumeric($campos[self::COL_RUT],
            'El primer campo debe ser el RUT numérico; no debe haber encabezado');
    }

    public function test_contiene_una_fila_por_trabajador(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        for ($i = 0; $i < 3; $i++) {
            $emp = $this->crearEmpleadoConContrato($empresa->id);
            $this->calcularYEmitir($empresa->id, $emp->id);
        }

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $lineas = $this->lineasArchivo($contenido);

        $this->assertCount(3, $lineas, 'Debe haber una fila por trabajador');
        foreach ($lineas as $linea) {
            $this->assertCount(self::TOTAL_CAMPOS, $this->parsearLinea($linea));
        }
    }

    // ── Tests tipo de movimiento ───────────────────────────────────────────────

    public function test_tipo_movimiento_0_para_mes_completo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Contrato inicia mucho antes del período → sin movimiento
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2024-01-01');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('0', $campos[self::COL_TIPO_MOV],
            'Mes completo sin inicio/término en el período → tipo movimiento 0');
    }

    public function test_tipo_movimiento_1_cuando_contrato_inicia_en_el_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Contrato inicia el 15 de junio 2026
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2026-06-15');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('1', $campos[self::COL_TIPO_MOV],
            'Contrato que inicia en el período → tipo movimiento 1 (contratación)');
    }

    public function test_tipo_movimiento_2_cuando_contrato_termina_en_el_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // El contrato se crea activo (para que LiquidacionService lo acepte),
        // se calcula y emite la liquidación, y LUEGO se registra el término
        // (igual que ocurre en producción: la liquidación precede al finiquito).
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2024-01-01');
        $this->calcularYEmitir($empresa->id, $emp->id);

        // Simular finiquito: registrar fecha_termino en el contrato
        Contrato::where('empleado_id', $emp->id)->update(['fecha_termino' => '2026-06-20']);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('2', $campos[self::COL_TIPO_MOV],
            'Contrato que termina en el período → tipo movimiento 2 (retiro)');
    }

    // ── Tests días trabajados ──────────────────────────────────────────────────

    public function test_dias_trabajados_30_para_mes_completo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2024-01-01');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        // Junio tiene 30 días; contrato vigente todo el mes → 30
        $this->assertEquals('30', $campos[self::COL_DIAS_TRABAJADOS],
            'Contrato vigente todo junio (30 días) → 30 días trabajados');
    }

    public function test_dias_trabajados_proporcionales_inicio_en_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // Contrato inicia el 16 de junio → 15 días (16..30)
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2026-06-16');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('15', $campos[self::COL_DIAS_TRABAJADOS],
            'Contrato que inicia el día 16 de junio → 15 días trabajados (16..30)');
    }

    public function test_dias_trabajados_proporcionales_termino_en_periodo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        // El contrato se calcula con contrato activo; después se registra el término
        $emp = $this->crearEmpleadoConContrato($empresa->id, fechaInicio: '2024-01-01');
        $this->calcularYEmitir($empresa->id, $emp->id);

        // Simular finiquito el día 10: registrar fecha_termino antes de generar Previred
        Contrato::where('empleado_id', $emp->id)->update(['fecha_termino' => '2026-06-10']);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('10', $campos[self::COL_DIAS_TRABAJADOS],
            'Contrato que termina el día 10 de junio → 10 días trabajados (1..10)');
    }

    // ── Tests códigos AFP y salud ──────────────────────────────────────────────

    public function test_codigo_afp_habitat_es_07(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, afp: 'Habitat');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('07', $campos[self::COL_AFP_CODIGO],
            'Habitat debe mapear al código AFP 07');
    }

    public function test_codigo_afp_uno_es_13(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, afp: 'Uno');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('13', $campos[self::COL_AFP_CODIGO],
            'AFP Uno debe mapear al código 13');
    }

    public function test_codigo_salud_fonasa_es_07(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoSalud: 'FONASA');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('07', $campos[self::COL_SALUD_CODIGO],
            'FONASA debe mapear al código salud 07');
    }

    // ── Tests AFC ─────────────────────────────────────────────────────────────

    public function test_tipo_afc_indefinido_es_1(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoContrato: 'INDEFINIDO');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('1', $campos[self::COL_TIPO_AFC],
            'Contrato indefinido → tipo AFC 1');
    }

    public function test_tipo_afc_plazo_fijo_es_2(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoContrato: 'PLAZO_FIJO');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('2', $campos[self::COL_TIPO_AFC],
            'Contrato a plazo fijo → tipo AFC 2');
    }

    // ── Tests cotización adicional ISAPRE ─────────────────────────────────────

    public function test_cotizacion_adicional_isapre_cero_para_fonasa(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, tipoSalud: 'FONASA');
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('0', $campos[self::COL_COT_ADIC_ISAPRE],
            'FONASA no tiene cotización adicional ISAPRE → debe ser 0');
    }

    public function test_cotizacion_adicional_isapre_positiva_cuando_plan_supera_7_pct(): void
    {
        // Sueldo 900 000; imponible salud aprox 900 000; 7% = 63 000
        // Plan ISAPRE = 2 UF × 39 850 = 79 700 > 63 000 → adicional ≈ 16 700
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato(
            $empresa->id,
            tipoSalud: 'ISAPRE',
            isapreNombre: 'Consalud',
            isapre_plan_uf: 2.0,
            sueldo: 900000
        );
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $adicional = (int) $campos[self::COL_COT_ADIC_ISAPRE];
        $this->assertGreaterThan(0, $adicional,
            'Cuando el plan UF supera el 7% de imponible debe haber cotización adicional > 0');
    }

    public function test_cotizacion_adicional_isapre_cero_cuando_plan_no_supera_7_pct(): void
    {
        // Sueldo alto 3 000 000; 7% = 210 000; plan 1 UF × 39 850 = 39 850 < 210 000
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato(
            $empresa->id,
            tipoSalud: 'ISAPRE',
            isapreNombre: 'Consalud',
            isapre_plan_uf: 1.0,
            sueldo: 3000000
        );
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('0', $campos[self::COL_COT_ADIC_ISAPRE],
            'Cuando el plan UF no supera el 7% de imponible la cotización adicional es 0');
    }

    // ── Tests período ─────────────────────────────────────────────────────────

    public function test_periodo_desde_y_hasta_en_formato_aaaamm(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('202606', $campos[self::COL_PERIODO_DESDE], 'Período desde = 202606');
        $this->assertEquals('202606', $campos[self::COL_PERIODO_HASTA], 'Período hasta = 202606');
    }

    // ── Tests montos ──────────────────────────────────────────────────────────

    public function test_liquido_pagar_coincide_con_liquidacion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id, afp: 'Habitat', sueldo: 1200000);
        $liq = $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $liquidoArchivo = (int) $campos[self::COL_LIQUIDO];
        $this->assertGreaterThan(0, $liquidoArchivo, 'Líquido debe ser positivo');
        $this->assertEqualsWithDelta(
            (float) $liq->liquido_a_pagar,
            $liquidoArchivo,
            1.0,
            'Líquido en archivo debe coincidir con el de la liquidación'
        );
    }

    public function test_regimen_previsional_es_1_para_afp(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $contenido = $this->service->generarArchivo($empresa->id, 2026, 6);
        $campos = $this->parsearLinea($this->lineasArchivo($contenido)[0]);

        $this->assertEquals('1', $campos[self::COL_REGIMEN],
            'Trabajadores AFP → régimen previsional 1');
    }

    // ── Tests de error y multitenant ──────────────────────────────────────────

    public function test_falla_si_no_hay_liquidaciones_emitidas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(RrhhException::class);
        $this->service->generarArchivo($empresa->id, 2026, 6);
    }

    public function test_aislamiento_multitenant(): void
    {
        [$empresaA] = $this->crearEmpresaConAdmin();
        [$empresaB] = $this->crearEmpresaConAdmin();

        $empA = $this->crearEmpleadoConContrato($empresaA->id);
        $this->calcularYEmitir($empresaA->id, $empA->id);

        $this->expectException(RrhhException::class);
        $this->service->generarArchivo($empresaB->id, 2026, 6);
    }

    // ── Tests de endpoints HTTP ────────────────────────────────────────────────

    public function test_endpoint_previred_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/rrhh/previred/2026/6/archivo');
        $response->assertStatus(401);
    }

    public function test_endpoint_previred_requiere_permiso_ver(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $rolSinPermiso = Rol::create([
            'nombre' => 'Sin Permiso',
            'jerarquia' => 10,
            'permisos' => [],
        ]);
        $usuario = $this->crearUsuario($empresa, $rolSinPermiso);

        $response = $this->actingAs($usuario)->getJson('/api/rrhh/previred/2026/6/archivo');
        $response->assertStatus(403);
    }

    public function test_nombre_archivo_descargado_es_txt(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $response = $this->actingAs($usuario)->get('/api/rrhh/previred/2026/6/archivo');
        $response->assertStatus(200);

        $disposition = $response->headers->get('Content-Disposition', '');
        $this->assertStringContainsString('.txt', $disposition,
            'El archivo descargado debe tener extensión .txt');
    }

    /** @test L-1: sexo M/F del empleado se vuelca al campo 6 del archivo. */
    public function test_campo_sexo_refleja_valor_del_empleado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '11.111.111-1',
            'nombres' => 'Maria',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Soto',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
            'sexo' => 'F',
            'nacionalidad' => 'CHL',
        ]);
        Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 800000,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
        $this->calcularYEmitir($empresa->id, $empleado->id);

        $campos = $this->parsearPrimeraFila($empresa);

        $this->assertEquals('F', $campos[self::COL_SEXO], 'Campo 6 debe ser F');
    }

    /** @test L-1: sexo 'O' (otro) queda vacío porque Previred no tiene ese código. */
    public function test_sexo_otro_queda_vacio_en_previred(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '22.222.222-2',
            'nombres' => 'Alex',
            'apellido_paterno' => 'Reyes',
            'apellido_materno' => 'Mora',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
            'sexo' => 'O',
            'nacionalidad' => 'CHL',
        ]);
        Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 800000,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
        $this->calcularYEmitir($empresa->id, $empleado->id);

        $campos = $this->parsearPrimeraFila($empresa);

        $this->assertEquals('', $campos[self::COL_SEXO], 'Campo 6 debe quedar vacío para sexo O');
    }

    /** @test L-1: nacionalidad ISO alpha-3 del empleado se vuelca al campo 7. */
    public function test_campo_nacionalidad_refleja_valor_del_empleado(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id' => $empresa->id,
            'rut' => '33.333.333-3',
            'nombres' => 'Pedro',
            'apellido_paterno' => 'Gomez',
            'apellido_materno' => 'Diaz',
            'afp' => 'Habitat',
            'tipo_salud' => 'FONASA',
            'sexo' => 'M',
            'nacionalidad' => 'ARG',
        ]);
        Contrato::create([
            'empresa_id' => $empresa->id,
            'empleado_id' => $empleado->id,
            'tipo' => 'INDEFINIDO',
            'fecha_inicio' => '2024-01-01',
            'sueldo_base' => 800000,
            'horas_semana' => 42,
            'estado' => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
        $this->calcularYEmitir($empresa->id, $empleado->id);

        $campos = $this->parsearPrimeraFila($empresa);

        $this->assertEquals('ARG', $campos[self::COL_NACIONALIDAD], 'Campo 7 debe ser ARG');
    }

    /** @test L-1: código de mutualidad se lee desde el parámetro previsional (campo 59). */
    public function test_campo_mutualidad_usa_codigo_del_parametro_previsional(): void
    {
        // El setUp de esta clase ya crea un ParametroPrevisional vigente sin mutual_codigo
        // (valor por defecto '01'). Creamos uno explícito con ISL = '02'.
        ParametroPrevisional::query()->update(['mutual_codigo' => '02']);

        [$empresa] = $this->crearEmpresaConAdmin();
        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $campos = $this->parsearPrimeraFila($empresa);

        // Campo 59 es índice 58 (base 0)
        $this->assertEquals('02', $campos[58], 'Campo 59 debe ser 02 (ISL)');
    }

    // Regresión: empresa->mutualidad debe tener prioridad sobre el parametro_previsional legacy,
    // porque la afiliación a mutualidad es una decisión por empresa, no un parámetro nacional único.
    public function test_campo_mutualidad_prioriza_mutualidad_de_la_empresa_sobre_parametro_legacy(): void
    {
        ParametroPrevisional::query()->update(['mutual_codigo' => '02']);

        $achs = Mutualidad::where('codigo_previred', '01')->first();

        [$empresa] = $this->crearEmpresaConAdmin();
        $empresa->update(['mutualidad_id' => $achs->id]);

        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $campos = $this->parsearPrimeraFila($empresa);

        $this->assertEquals('01', $campos[58], 'Campo 59 debe usar el codigo de empresa.mutualidad (01=ACHS), no el legacy (02)');
    }

    // Regresión: empresa afiliada a IST/Mutual CChC (codigo_previred null, sin especificación
    // oficial confirmada) NO debe caer silenciosamente al parametro previsional legacy/global,
    // que representa la eleccion de OTRA empresa, no la de esta.
    public function test_campo_mutualidad_con_codigo_previred_desconocido_no_usa_legacy_de_otra_empresa(): void
    {
        // Simula el legacy global apuntando a un codigo que NO corresponde a esta empresa.
        ParametroPrevisional::query()->update(['mutual_codigo' => '02']);

        $ist = Mutualidad::where('nombre', 'IST')->first();
        $this->assertNull($ist->codigo_previred, 'Precondicion: IST debe tener codigo_previred null en el seed.');

        [$empresa] = $this->crearEmpresaConAdmin();
        $empresa->update(['mutualidad_id' => $ist->id]);

        $emp = $this->crearEmpleadoConContrato($empresa->id);
        $this->calcularYEmitir($empresa->id, $emp->id);

        $campos = $this->parsearPrimeraFila($empresa);

        $this->assertNotEquals('02', $campos[58], 'No debe usar el codigo legacy global (02), que no representa la eleccion de esta empresa.');
        $this->assertEquals('01', $campos[58], 'Debe caer al default aproximado documentado (01), no a un valor de otra empresa.');
    }

    private function parsearPrimeraFila(object $empresa): array
    {
        $service = app(PreviredService::class);
        $archivo = $service->generarArchivo($empresa->id, 2026, 6);
        $lineas = explode("\r\n", trim($archivo));

        return explode(';', $lineas[0]);
    }
}
