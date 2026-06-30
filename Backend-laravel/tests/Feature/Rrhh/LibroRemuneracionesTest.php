<?php

namespace Tests\Feature\Rrhh;

use App\Domains\Core\Models\Rol;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * R9 — Libro de Remuneraciones Digital.
 *
 * DFL-1 Art. 62 Código del Trabajo:
 * - Obligatorio para empresas con 5+ trabajadores.
 * - Formato digital desde 2022 (Resolución Exenta N° 738 DT).
 *
 * Valida:
 * - Estructura correcta del JSON (filas, totales, empresa, periodo, cantidad_trabajadores).
 * - Coherencia matemática: totales = suma de filas.
 * - Período sin liquidaciones retorna lista vacía.
 * - Descarga Excel y PDF responden 200 con Content-Type correcto.
 * - Aislamiento multitenant: empresa A no ve el libro de empresa B.
 */
class LibroRemuneracionesTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Crea un empleado con contrato y una liquidación EMITIDA con detalles mínimos.
     *
     * @return array{0: \App\Domains\Rrhh\Models\Empleado, 1: \App\Domains\Rrhh\Models\Liquidacion}
     */
    private function crearEmpleadoConLiquidacion(
        int    $empresaId,
        string $rut           = '12.345.678-9',
        int    $sueldoBase    = 800000,
        int    $totalHaberes  = 850000,
        int    $liquido       = 700000,
        int    $descLegales   = 120000,
        int    $descVolunt    = 30000,
    ): array {
        $empleado = Empleado::create([
            'empresa_id'            => $empresaId,
            'rut'                   => $rut,
            'nombres'               => 'Juan',
            'apellido_paterno'      => 'Pérez',
            'apellido_materno'      => 'López',
            'afp'                   => 'Habitat',
            'tipo_salud'            => 'FONASA',
            'estado'                => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id'         => $empresaId,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'fecha_inicio'       => '2024-01-01',
            'sueldo_base'        => $sueldoBase,
            'cargo'              => 'Vendedor',
            'horas_semana'       => 45,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liq = Liquidacion::create([
            'empresa_id'                   => $empresaId,
            'empleado_id'                  => $empleado->id,
            'contrato_id'                  => $contrato->id,
            'anio'                         => 2026,
            'mes'                          => 6,
            'estado'                       => Liquidacion::ESTADO_EMITIDA,
            'total_haberes_imponibles'     => $sueldoBase,
            'total_haberes_no_imponibles'  => $totalHaberes - $sueldoBase,
            'total_haberes'                => $totalHaberes,
            'base_imponible'               => $sueldoBase,
            'base_tributable'              => (int) ($sueldoBase * 0.9),
            'total_descuentos_legales'     => $descLegales,
            'total_descuentos_voluntarios' => $descVolunt,
            'total_descuentos'             => $descLegales + $descVolunt,
            'liquido_a_pagar'              => $liquido,
            'aporte_empleador_afc'         => 19200,
            'aporte_empleador_sis'         => 12960,
            'aporte_empleador_mutual'      => 7200,
            'aporte_empleador_reforma'     => 8000,
            'salud_legal'                  => 56000,
            'salud_adicional'              => 0,
            'dias_trabajados'              => 30,
        ]);

        // Detalles para que el servicio pueda extraer conceptos individuales
        LiquidacionDetalle::create([
            'empresa_id'      => $empresaId,
            'liquidacion_id'  => $liq->id,
            'codigo_concepto' => 'SUELDO_BASE',
            'nombre_concepto' => 'Sueldo Base',
            'tipo'            => 'HABER_IMPONIBLE',
            'monto'           => $sueldoBase,
            'orden'           => 100,
        ]);
        LiquidacionDetalle::create([
            'empresa_id'      => $empresaId,
            'liquidacion_id'  => $liq->id,
            'codigo_concepto' => 'AFP_COTIZACION',
            'nombre_concepto' => 'Cotización AFP',
            'tipo'            => 'DESCUENTO_LEGAL',
            'monto'           => 80000,
            'orden'           => 200,
        ]);
        LiquidacionDetalle::create([
            'empresa_id'      => $empresaId,
            'liquidacion_id'  => $liq->id,
            'codigo_concepto' => 'SALUD',
            'nombre_concepto' => 'Cotización Salud',
            'tipo'            => 'DESCUENTO_LEGAL',
            'monto'           => 56000,
            'orden'           => 210,
        ]);
        LiquidacionDetalle::create([
            'empresa_id'      => $empresaId,
            'liquidacion_id'  => $liq->id,
            'codigo_concepto' => 'AFC_TRABAJADOR',
            'nombre_concepto' => 'AFC Trabajador',
            'tipo'            => 'DESCUENTO_LEGAL',
            'monto'           => 4800,
            'orden'           => 220,
        ]);
        LiquidacionDetalle::create([
            'empresa_id'      => $empresaId,
            'liquidacion_id'  => $liq->id,
            'codigo_concepto' => 'IMPUESTO_UNICO',
            'nombre_concepto' => 'Impuesto Único 2ª Cat.',
            'tipo'            => 'DESCUENTO_LEGAL',
            'monto'           => 0,
            'orden'           => 230,
        ]);

        return [$empleado, $liq];
    }

    // ── Tests de estructura JSON ───────────────────────────────────────────────

    public function test_simular_retorna_estructura_correcta(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $this->crearEmpleadoConLiquidacion($empresa->id, '12.345.678-9');
        $this->crearEmpleadoConLiquidacion($empresa->id, '22.345.678-0');

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'empresa'  => ['rut', 'razon_social'],
                'periodo',
                'filas'    => [['rut', 'nombre', 'cargo', 'dias_trabajados',
                                'sueldo_base', 'horas_extras', 'total_haberes',
                                'descuento_previsional', 'descuento_legal',
                                'otros_descuentos', 'total_descuentos', 'liquido']],
                'totales'  => ['sueldo_base', 'horas_extras', 'total_haberes',
                               'descuento_previsional', 'descuento_legal',
                               'otros_descuentos', 'total_descuentos', 'liquido'],
                'cantidad_trabajadores',
            ]);

        $response->assertJsonPath('cantidad_trabajadores', 2);
        $this->assertCount(2, $response->json('filas'));
    }

    public function test_totales_son_suma_de_filas(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Dos empleados con distintos montos
        $this->crearEmpleadoConLiquidacion($empresa->id, '11.111.111-1',
            sueldoBase: 700000, totalHaberes: 730000, liquido: 580000,
            descLegales: 120000, descVolunt: 30000
        );
        $this->crearEmpleadoConLiquidacion($empresa->id, '22.222.222-2',
            sueldoBase: 1000000, totalHaberes: 1050000, liquido: 850000,
            descLegales: 160000, descVolunt: 40000
        );

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(200);

        $filas   = $response->json('filas');
        $totales = $response->json('totales');

        $sumaHaberes    = array_sum(array_column($filas, 'total_haberes'));
        $sumaLiquido    = array_sum(array_column($filas, 'liquido'));
        $sumaDescTot    = array_sum(array_column($filas, 'total_descuentos'));
        $sumaDescPrev   = array_sum(array_column($filas, 'descuento_previsional'));
        $sumaDescLegal  = array_sum(array_column($filas, 'descuento_legal'));
        $sumaOtrosDesc  = array_sum(array_column($filas, 'otros_descuentos'));
        $sumaBase       = array_sum(array_column($filas, 'sueldo_base'));

        $this->assertEquals($sumaHaberes,   $totales['total_haberes'],          'Total haberes no cuadra');
        $this->assertEquals($sumaLiquido,   $totales['liquido'],                'Liquido no cuadra');
        $this->assertEquals($sumaDescTot,   $totales['total_descuentos'],       'Total descuentos no cuadra');
        $this->assertEquals($sumaDescPrev,  $totales['descuento_previsional'],  'Desc. previsional no cuadra');
        $this->assertEquals($sumaDescLegal, $totales['descuento_legal'],        'Desc. legal no cuadra');
        $this->assertEquals($sumaOtrosDesc, $totales['otros_descuentos'],       'Otros desc. no cuadra');
        $this->assertEquals($sumaBase,      $totales['sueldo_base'],            'Sueldo base no cuadra');
    }

    public function test_periodo_sin_liquidaciones_retorna_vacio(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Mes 5 sin liquidaciones (existen en mes 6 pero pedimos mes 5)
        $this->crearEmpleadoConLiquidacion($empresa->id);

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/5');

        $response->assertStatus(200)
            ->assertJsonPath('cantidad_trabajadores', 0)
            ->assertJsonPath('filas', []);
    }

    // ── Tests de descarga ─────────────────────────────────────────────────────

    public function test_descargar_excel_responde_200(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearEmpleadoConLiquidacion($empresa->id);

        $response = $this->actingAs($usuario)
            ->get('/api/rrhh/libro-remuneraciones/2026/6/descargar?formato=excel');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/vnd.ms-excel',
            (string) $response->headers->get('Content-Type', '')
        );
    }

    public function test_descargar_pdf_responde_200(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $this->crearEmpleadoConLiquidacion($empresa->id);

        $response = $this->actingAs($usuario)
            ->get('/api/rrhh/libro-remuneraciones/2026/6/descargar?formato=pdf');

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('Content-Type', '')
        );
    }

    // ── Tests de aislamiento multitenant ──────────────────────────────────────

    public function test_aislamiento_multitenant(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        [$empresaB, $usuarioB] = $this->crearEmpresaConAdmin();

        // Solo empresa A tiene liquidaciones
        $this->crearEmpleadoConLiquidacion($empresaA->id, '12.345.678-9');
        $this->crearEmpleadoConLiquidacion($empresaA->id, '22.345.678-0');

        // El usuario B pide su propio libro → debe ver 0 filas (no ve las de A)
        $response = $this->actingAs($usuarioB)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(200)
            ->assertJsonPath('cantidad_trabajadores', 0)
            ->assertJsonPath('filas', []);
    }

    // ── Tests de acceso/permisos ──────────────────────────────────────────────

    public function test_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/rrhh/libro-remuneraciones/2026/6');
        $response->assertStatus(401);
    }

    public function test_requiere_permiso_rrhh_ver(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $rolSinPermiso = Rol::create([
            'nombre'    => 'Sin Permiso',
            'jerarquia' => 10,
            'permisos'  => [],
        ]);
        $usuario = $this->crearUsuario($empresa, $rolSinPermiso);

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(403);
    }

    public function test_periodo_invalido_retorna_422(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/1999/6');

        $response->assertStatus(422);
    }

    // ── Tests de contenido ────────────────────────────────────────────────────

    public function test_datos_de_empleado_y_contrato_se_incluyen(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        $empleado = Empleado::create([
            'empresa_id'            => $empresa->id,
            'rut'                   => '15.234.567-8',
            'nombres'               => 'María',
            'apellido_paterno'      => 'González',
            'apellido_materno'      => 'Rojas',
            'afp'                   => 'Capital',
            'tipo_salud'            => 'FONASA',
            'estado'                => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id'         => $empresa->id,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'fecha_inicio'       => '2024-01-01',
            'sueldo_base'        => 650000,
            'cargo'              => 'Contadora',
            'horas_semana'       => 45,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        Liquidacion::create([
            'empresa_id'                   => $empresa->id,
            'empleado_id'                  => $empleado->id,
            'contrato_id'                  => $contrato->id,
            'anio'                         => 2026,
            'mes'                          => 6,
            'estado'                       => Liquidacion::ESTADO_EMITIDA,
            'total_haberes_imponibles'     => 650000,
            'total_haberes_no_imponibles'  => 0,
            'total_haberes'                => 650000,
            'base_imponible'               => 650000,
            'base_tributable'              => 585000,
            'total_descuentos_legales'     => 90000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos'             => 90000,
            'liquido_a_pagar'              => 560000,
            'aporte_empleador_afc'         => 15600,
            'aporte_empleador_sis'         => 10530,
            'aporte_empleador_mutual'      => 5850,
            'aporte_empleador_reforma'     => 6500,
            'salud_legal'                  => 45500,
            'salud_adicional'              => 0,
            'dias_trabajados'              => 30,
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(200);
        $fila = $response->json('filas.0');

        $this->assertStringContainsString('González', $fila['nombre']);
        $this->assertEquals('Contadora', $fila['cargo']);
        $this->assertEquals('15.234.567-8', $fila['rut']);
        $this->assertEquals(30, $fila['dias_trabajados']);
    }

    public function test_solo_incluye_liquidaciones_emitidas_y_pagadas(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Liquidación EMITIDA → debe aparecer
        $this->crearEmpleadoConLiquidacion($empresa->id, '11.111.111-1');

        // Liquidación BORRADOR → no debe aparecer
        $emp2 = Empleado::create([
            'empresa_id'            => $empresa->id,
            'rut'                   => '22.222.222-2',
            'nombres'               => 'Pedro',
            'apellido_paterno'      => 'Soto',
            'afp'                   => 'Capital',
            'tipo_salud'            => 'FONASA',
            'estado'                => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);
        $c2 = Contrato::create([
            'empresa_id'         => $empresa->id,
            'empleado_id'        => $emp2->id,
            'tipo'               => 'INDEFINIDO',
            'fecha_inicio'       => '2024-01-01',
            'sueldo_base'        => 700000,
            'horas_semana'       => 45,
            'estado'             => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);
        Liquidacion::create([
            'empresa_id'                   => $empresa->id,
            'empleado_id'                  => $emp2->id,
            'contrato_id'                  => $c2->id,
            'anio'                         => 2026,
            'mes'                          => 6,
            'estado'                       => Liquidacion::ESTADO_BORRADOR,
            'total_haberes_imponibles'     => 700000,
            'total_haberes_no_imponibles'  => 0,
            'total_haberes'                => 700000,
            'base_imponible'               => 700000,
            'base_tributable'              => 630000,
            'total_descuentos_legales'     => 100000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos'             => 100000,
            'liquido_a_pagar'              => 600000,
            'aporte_empleador_afc'         => 16800,
            'aporte_empleador_sis'         => 11340,
            'aporte_empleador_mutual'      => 6300,
            'aporte_empleador_reforma'     => 7000,
            'salud_legal'                  => 49000,
            'salud_adicional'              => 0,
        ]);

        $response = $this->actingAs($usuario)
            ->getJson('/api/rrhh/libro-remuneraciones/2026/6');

        $response->assertStatus(200)
            ->assertJsonPath('cantidad_trabajadores', 1);
        $this->assertCount(1, $response->json('filas'));
    }
}
