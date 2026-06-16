<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Models\DjEnvio;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use App\Domains\Rrhh\Models\LiquidacionDetalle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1887Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        Storage::fake('sii_xml');
    }

    // ------------------------------------------------------------------
    // Helpers de setup
    // ------------------------------------------------------------------

    /**
     * Crea un empleado con contrato y una o más liquidaciones EMITIDAS.
     *
     * @param int   $empresaId
     * @param string $rut
     * @param array  $meses          Lista de meses para los que crear liquidaciones
     * @param array  $overridesLiq   Overrides opcionales para la liquidación
     */
    private function crearEmpleadoConLiquidaciones(
        int $empresaId,
        string $rut,
        array $meses = [1, 2],
        array $overridesLiq = []
    ): array {
        $empleado = Empleado::create([
            'empresa_id'            => $empresaId,
            'rut'                   => $rut,
            'nombres'               => 'Juan',
            'apellido_paterno'      => 'Pérez',
            'afp'                   => 'Capital',
            'tipo_salud'            => 'FONASA',
            'estado'                => 'ACTIVO',
            'fecha_ingreso_empresa' => '2024-01-01',
        ]);

        $contrato = Contrato::create([
            'empresa_id'         => $empresaId,
            'empleado_id'        => $empleado->id,
            'tipo'               => 'INDEFINIDO',
            'tipo_jornada'       => 'COMPLETA',
            'fecha_inicio'       => '2024-01-01',
            'sueldo_base'        => 800000,
            'horas_semana'       => 45,
            'estado'             => 'ACTIVO',
            'es_contrato_activo' => true,
        ]);

        $liqs = [];
        foreach ($meses as $mes) {
            $liq = Liquidacion::create(array_merge([
                'empresa_id'                   => $empresaId,
                'empleado_id'                  => $empleado->id,
                'contrato_id'                  => $contrato->id,
                'anio'                         => 2024,
                'mes'                          => $mes,
                'estado'                       => Liquidacion::ESTADO_EMITIDA,
                'total_haberes_imponibles'     => 900000,
                'total_haberes_no_imponibles'  => 50000,
                'total_haberes'                => 950000,
                'base_imponible'               => 900000,
                'base_tributable'              => 810000,
                'total_descuentos_legales'     => 135000,
                'total_descuentos_voluntarios' => 0,
                'total_descuentos'             => 135000,
                'liquido_a_pagar'              => 815000,
                'aporte_empleador_afc'         => 21600,
                'aporte_empleador_sis'         => 14580,
                'aporte_empleador_mutual'      => 8100,
                'aporte_empleador_reforma'     => 9000,
                'salud_legal'                  => 63000,
                'salud_adicional'              => 0,
                'dias_trabajados'              => 30,
                'dias_licencia_medica'         => 0,
                'dias_vacaciones'              => 0,
            ], $overridesLiq, ['mes' => $mes]));

            // Detalle: IUSC (impuesto único segunda categoría)
            LiquidacionDetalle::create([
                'empresa_id'      => $empresaId,
                'liquidacion_id'  => $liq->id,
                'codigo_concepto' => 'IMPUESTO_UNICO',
                'nombre_concepto' => 'Impuesto Único 2da Cat.',
                'tipo'            => 'DESCUENTO_LEGAL',
                'monto'           => 10000,
                'orden'           => 300,
            ]);

            $liqs[] = $liq;
        }

        return [$empleado, $contrato, $liqs];
    }

    /** Crea empresa con usuario SuperAdmin (jerarquía 100 → todos los permisos). */
    private function crearEmpresaConSuperAdmin(): array
    {
        [$empresa, $usuarioAdmin] = $this->crearEmpresaConAdmin();
        // Reasignar al rol SuperAdmin para tener contabilidad.dj.ver y contabilidad.dj.procesar
        $usuarioAdmin->update(['rol_id' => $this->rolSuperAdmin->id]);
        return [$empresa, $usuarioAdmin];
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    public function test_generar_crea_dj_envio_con_estado_generado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones($empresa->id, '12345678-9', [1, 2]);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', [
                'anio'          => 2024,
                'anio_40_horas' => 2026,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('dj_envios', [
            'empresa_id'   => $empresa->id,
            'codigo_dj'    => '1887',
            'anio'         => 2024,
            'estado'       => DjEnvio::ESTADO_GENERADO,
            'anio_40_horas' => 2026,
        ]);
    }

    public function test_generar_es_idempotente_para_mismo_anio(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones($empresa->id, '12345678-9', [1]);

        $this->actingAs($usuario)->postJson('/api/dj/1887/generar', ['anio' => 2024]);
        $this->actingAs($usuario)->postJson('/api/dj/1887/generar', ['anio' => 2024]);

        $count = DjEnvio::where('empresa_id', $empresa->id)
            ->where('codigo_dj', '1887')
            ->where('anio', 2024)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_generar_sin_liquidaciones_crea_envio_vacio(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        // Sin liquidaciones

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', ['anio' => 2024]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('dj_envios', [
            'empresa_id'        => $empresa->id,
            'codigo_dj'         => '1887',
            'anio'              => 2024,
            'cantidad_registros' => 0,
        ]);
    }

    public function test_validar_retorna_valido_true_con_datos_correctos(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones($empresa->id, '12345678-9', [1, 2]);

        // Generar primero
        $generar = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', ['anio' => 2024]);
        $generar->assertStatus(201);

        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->postJson("/api/dj/1887/{$envioId}/validar");

        $response->assertStatus(200)
            ->assertJson(['valido' => true, 'errores' => []]);
    }

    public function test_validar_retorna_errores_cuando_renta_cero(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones(
            $empresa->id,
            '12345678-9',
            [1],
            ['base_tributable' => 0]
        );

        $generar = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', ['anio' => 2024]);
        $generar->assertStatus(201);

        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->postJson("/api/dj/1887/{$envioId}/validar");

        $response->assertStatus(200);
        $this->assertFalse($response->json('valido'));
        $this->assertNotEmpty($response->json('errores'));

        // El mensaje debe mencionar renta cero o negativa
        $errores = implode(' ', $response->json('errores'));
        $this->assertStringContainsString('renta neta es cero', $errores);
    }

    public function test_descargar_retorna_archivo_txt(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones($empresa->id, '12345678-9', [1]);

        $generar = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', ['anio' => 2024]);
        $generar->assertStatus(201);

        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->get("/api/dj/1887/{$envioId}/descargar");

        $response->assertStatus(200);

        $contentType = $response->headers->get('Content-Type');
        $this->assertTrue(
            str_contains($contentType, 'text/plain')
            || str_contains($contentType, 'application/octet-stream'),
            "Content-Type inesperado: {$contentType}"
        );
    }

    public function test_confirmar_presentacion_actualiza_estado_a_presentado(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();
        $this->crearEmpleadoConLiquidaciones($empresa->id, '12345678-9', [1]);

        $generar = $this->actingAs($usuario)
            ->postJson('/api/dj/1887/generar', ['anio' => 2024]);
        $generar->assertStatus(201);

        $envioId = $generar->json('data.id');

        $response = $this->actingAs($usuario)
            ->postJson("/api/dj/1887/{$envioId}/confirmar-presentacion", [
                'folio_presentacion' => '12345',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'DJ 1887 marcada como presentada.');

        $envio = DjEnvio::find($envioId);
        $this->assertEquals(DjEnvio::ESTADO_PRESENTADO, $envio->estado);
        $this->assertEquals('12345', $envio->folio_presentacion);
        $this->assertNotNull($envio->presentado_at);
    }

    public function test_rutas_requieren_autenticacion(): void
    {
        $this->postJson('/api/dj/1887/generar', ['anio' => 2024])
            ->assertStatus(401);

        $this->getJson('/api/dj/1887/')
            ->assertStatus(401);

        // Para las rutas con model binding usamos un ID ficticio (el 401 se lanza antes del binding)
        $this->postJson('/api/dj/1887/999/validar')
            ->assertStatus(401);

        $this->getJson('/api/dj/1887/999/descargar')
            ->assertStatus(401);

        $this->postJson('/api/dj/1887/999/confirmar-presentacion')
            ->assertStatus(401);
    }

    /** Crea empresa con usuario SuperAdmin para tests de aislamiento multitenant. */
    private function crearEmpresaConUsuario(): array
    {
        $empresa = $this->crearEmpresa();
        $usuario = $this->crearUsuario($empresa, $this->rolSuperAdmin);
        return [$empresa, $usuario];
    }

    public function test_usuario_empresa_b_no_puede_acceder_a_dj_envio_de_empresa_a(): void
    {
        Storage::fake('sii_xml');

        // Empresa A con un DjEnvio
        [$empresaA, $usuarioA] = $this->crearEmpresaConUsuario();
        $this->actingAs($usuarioA);
        $this->postJson('/api/dj/1887/generar', ['anio' => 2024]);

        $envioA = DjEnvio::where('empresa_id', $empresaA->id)->first();
        $this->assertNotNull($envioA);

        // Empresa B intenta acceder al DjEnvio de A
        [$empresaB, $usuarioB] = $this->crearEmpresaConUsuario();
        $this->actingAs($usuarioB);

        // El EmpresaScope global retorna 404 antes de que llegue al abort_unless del controller.
        // Ambos códigos (403 o 404) garantizan el aislamiento multitenant: empresa B no puede acceder.
        $statusValidar = $this->postJson("/api/dj/1887/{$envioA->id}/validar")->status();
        $this->assertContains($statusValidar, [403, 404], "Esperado 403 o 404, recibido {$statusValidar}");

        $statusDescargar = $this->getJson("/api/dj/1887/{$envioA->id}/descargar")->status();
        $this->assertContains($statusDescargar, [403, 404], "Esperado 403 o 404, recibido {$statusDescargar}");

        $statusConfirmar = $this->postJson("/api/dj/1887/{$envioA->id}/confirmar-presentacion", [])->status();
        $this->assertContains($statusConfirmar, [403, 404], "Esperado 403 o 404, recibido {$statusConfirmar}");
    }
}
