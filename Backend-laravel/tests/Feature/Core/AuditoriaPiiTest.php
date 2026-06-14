<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Auditoria;
use App\Domains\Rrhh\Models\Contrato;
use App\Domains\Rrhh\Models\Empleado;
use App\Domains\Rrhh\Models\Liquidacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 3 (Ley 21.719) — Auditoria PII.
 *
 * Verifica que:
 *  1. El observer escribe filas de auditoria en CREAR / ACTUALIZAR / ELIMINAR.
 *  2. Ninguna fila almacena valores PII (solo nombres de campos).
 *  3. El endpoint DPO /api/auditoria aplica filtrado multi-tenant y RBAC.
 *  4. El registro de LECTURA en LiquidacionController respeta la flag.
 */
class AuditoriaPiiTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // -----------------------------------------------------------------------
    // TASK 1 — Observer CREAR / ACTUALIZAR / ELIMINAR
    // -----------------------------------------------------------------------

    public function test_crear_empleado_escribe_fila_auditoria_crear(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        // Actuar como el admin para que auth()->user() devuelva su nombre
        $this->actingAs($admin);

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '12.345.678-9',
            'nombres'          => 'Juan',
            'apellido_paterno' => 'Test',
            'afp'              => 'Habitat',
            'tipo_salud'       => 'FONASA',
        ]);

        $fila = Auditoria::where('auditable_type', Empleado::class)
            ->where('auditable_id', $empleado->id)
            ->where('operacion', 'CREAR')
            ->first();

        $this->assertNotNull($fila, 'Debe existir una fila de auditoria CREAR para el Empleado.');
        $this->assertEquals($admin->nombre, $fila->nombre_usuario);
        $this->assertEquals('Registro creado', $fila->detalle);
        $this->assertNull($fila->estado_anterior, 'estado_anterior debe ser null (no se almacenan valores PII).');
        $this->assertNull($fila->estado_nuevo, 'estado_nuevo debe ser null (no se almacenan valores PII).');
    }

    public function test_actualizar_email_empleado_escribe_auditoria_con_nombre_de_campo_no_valor(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->actingAs($admin);

        $emailPii = 'secreto.real@empresa.cl';

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '11.111.111-1',
            'nombres'          => 'Ana',
            'apellido_paterno' => 'García',
            'email'            => 'original@empresa.cl',
            'afp'              => 'Modelo',
            'tipo_salud'       => 'FONASA',
        ]);

        // Actualizar solo el email
        $empleado->update(['email' => $emailPii]);

        $fila = Auditoria::where('auditable_type', Empleado::class)
            ->where('auditable_id', $empleado->id)
            ->where('operacion', 'ACTUALIZAR')
            ->latest()
            ->first();

        $this->assertNotNull($fila, 'Debe existir una fila de auditoria ACTUALIZAR para el Empleado.');

        // Debe mencionar el nombre del campo 'email' en el detalle
        $this->assertStringContainsString('email', $fila->detalle,
            'El detalle debe listar el nombre del campo modificado.');

        // CRITICO: el valor real del email NO debe aparecer en ninguna columna de la fila
        $filaJson = json_encode($fila->toArray());
        $this->assertStringNotContainsString($emailPii, $filaJson,
            'El valor PII del email NO debe aparecer en ninguna columna de la fila de auditoria.');

        // estado_anterior y estado_nuevo deben ser siempre null
        $this->assertNull($fila->estado_anterior);
        $this->assertNull($fila->estado_nuevo);
    }

    public function test_eliminar_empleado_escribe_fila_auditoria_eliminar(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->actingAs($admin);

        $empleado = Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '22.222.222-2',
            'nombres'          => 'Pedro',
            'apellido_paterno' => 'Soto',
            'afp'              => 'Capital',
            'tipo_salud'       => 'FONASA',
        ]);
        $empleadoId = $empleado->id;

        $empleado->delete(); // SoftDelete

        $fila = Auditoria::where('auditable_type', Empleado::class)
            ->where('auditable_id', $empleadoId)
            ->where('operacion', 'ELIMINAR')
            ->first();

        $this->assertNotNull($fila, 'Debe existir una fila de auditoria ELIMINAR para el Empleado.');
        $this->assertEquals('Registro eliminado', $fila->detalle);
    }

    // -----------------------------------------------------------------------
    // TASK 3 — DPO endpoint /api/auditoria (multi-tenant + RBAC)
    // -----------------------------------------------------------------------

    public function test_admin_accede_a_auditoria_de_su_empresa(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->actingAs($admin);

        // Crear un empleado para que se genere una fila de auditoria
        Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '44.444.444-4',
            'nombres'          => 'Roberto',
            'apellido_paterno' => 'Vega',
            'afp'              => 'Habitat',
            'tipo_salud'       => 'FONASA',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/auditoria');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Debe haber al menos la fila CREAR del empleado
        $total = $response->json('data.total');
        $this->assertGreaterThanOrEqual(1, $total,
            'El admin debe ver al menos las filas de auditoria de su empresa.');
    }

    public function test_usuario_sin_permiso_recibe_403_en_endpoint_auditoria(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $usuarioBasico = $this->crearUsuario($empresa, $this->rolUsuarioBasico);

        $response = $this->actingAs($usuarioBasico)->getJson('/api/auditoria');

        $response->assertStatus(403);
    }

    public function test_admin_empresa_b_no_ve_filas_de_empresa_a(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin([], ['email' => 'adminA@test.cl']);
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin([], ['email' => 'adminB@test.cl']);

        // Crear actividad en empresa A
        $this->actingAs($adminA);
        Empleado::create([
            'empresa_id'       => $empresaA->id,
            'rut'              => '55.555.555-5',
            'nombres'          => 'Secreto',
            'apellido_paterno' => 'EmpresaA',
            'afp'              => 'Modelo',
            'tipo_salud'       => 'FONASA',
        ]);

        // Admin de empresa B consulta el endpoint
        $response = $this->actingAs($adminB)->getJson('/api/auditoria');

        $response->assertStatus(200);

        // Las filas que ve deben pertenecer solo a empresa B (referencia_cruzada = empresa B id)
        $filas = $response->json('data.data');
        foreach ($filas as $fila) {
            $this->assertEquals(
                (string) $empresaB->id,
                $fila['referencia_cruzada'],
                'El admin de empresa B no debe ver filas de auditoria de empresa A.'
            );
        }
    }

    public function test_filtro_por_operacion_funciona(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->actingAs($admin);

        Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '66.666.666-6',
            'nombres'          => 'Filtro',
            'apellido_paterno' => 'Test',
            'afp'              => 'Habitat',
            'tipo_salud'       => 'FONASA',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/auditoria?operacion=CREAR');

        $response->assertStatus(200);
        $filas = $response->json('data.data');
        foreach ($filas as $fila) {
            $this->assertEquals('CREAR', $fila['operacion']);
        }
    }

    public function test_filtro_por_alias_auditable_type_funciona(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->actingAs($admin);

        Empleado::create([
            'empresa_id'       => $empresa->id,
            'rut'              => '77.777.777-7',
            'nombres'          => 'Alias',
            'apellido_paterno' => 'Test',
            'afp'              => 'Capital',
            'tipo_salud'       => 'FONASA',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/auditoria?auditable_type=empleado');

        $response->assertStatus(200);
        $filas = $response->json('data.data');
        $this->assertNotEmpty($filas, 'Debe haber filas de tipo empleado.');
        foreach ($filas as $fila) {
            $this->assertEquals(Empleado::class, $fila['auditable_type']);
        }
    }

    // -----------------------------------------------------------------------
    // TASK 2 — Read logging en LiquidacionController::show
    // -----------------------------------------------------------------------

    /**
     * Crea un Empleado + Contrato + Liquidacion minima para el test de lectura.
     * Liquidacion requiere contrato_id NOT NULL por la FK de la migration.
     */
    private function crearLiquidacionMinima(int $empresaId, string $rut, int $anio, int $mes): array
    {
        $empleado = Empleado::create([
            'empresa_id'       => $empresaId,
            'rut'              => $rut,
            'nombres'          => 'Empleado',
            'apellido_paterno' => 'LiqTest',
            'afp'              => 'Modelo',
            'tipo_salud'       => 'FONASA',
        ]);

        $contrato = Contrato::create([
            'empresa_id'       => $empresaId,
            'empleado_id'      => $empleado->id,
            'tipo'             => 'INDEFINIDO',
            'fecha_inicio'     => '2024-01-01',
            'sueldo_base'      => 1000000,
            'horas_semana'     => 45,
            'estado'           => 'VIGENTE',
            'es_contrato_activo' => true,
        ]);

        $liquidacion = Liquidacion::create([
            'empresa_id'                   => $empresaId,
            'empleado_id'                  => $empleado->id,
            'contrato_id'                  => $contrato->id,
            'anio'                         => $anio,
            'mes'                          => $mes,
            'total_haberes_imponibles'     => 1000000,
            'total_haberes_no_imponibles'  => 0,
            'total_haberes'                => 1000000,
            'base_imponible'               => 1000000,
            'base_tributable'              => 1000000,
            'total_descuentos_legales'     => 200000,
            'total_descuentos_voluntarios' => 0,
            'total_descuentos'             => 200000,
            'liquido_a_pagar'              => 800000,
            'aporte_empleador_afc'         => 24000,
            'aporte_empleador_sis'         => 0,
            'aporte_empleador_mutual'      => 9000,
            'salud_legal'                  => 70000,
            'salud_adicional'              => 0,
            'aporte_empleador_reforma'     => 0,
            'estado'                       => 'EMITIDA',
        ]);

        return [$empleado, $contrato, $liquidacion];
    }

    public function test_lectura_liquidacion_con_flag_activa_escribe_fila_lectura(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        [$empleado, , $liquidacion] = $this->crearLiquidacionMinima($empresa->id, '88.888.888-8', 2026, 6);

        // Asegurar flag activa
        config(['auditoria.lectura_pii' => true]);

        $response = $this->actingAs($admin)->getJson("/api/rrhh/liquidaciones/{$liquidacion->id}");
        $response->assertStatus(200);

        $fila = Auditoria::where('auditable_type', Liquidacion::class)
            ->where('auditable_id', $liquidacion->id)
            ->where('operacion', 'LECTURA')
            ->first();

        $this->assertNotNull($fila, 'Debe existir una fila de auditoria LECTURA para la Liquidacion.');
        $this->assertStringContainsString('liquidación', $fila->detalle);
        $this->assertStringContainsString((string) $empleado->id, $fila->detalle,
            'El detalle debe incluir el ID del empleado (no su nombre ni RUT).');
        $this->assertEquals((string) $empresa->id, $fila->referencia_cruzada);

        // Verificar que el nombre/RUT del empleado NO está en el detalle
        $this->assertStringNotContainsString('LiqTest', $fila->detalle,
            'El apellido del empleado no debe aparecer en el detalle de lectura.');
        $this->assertStringNotContainsString('88.888.888-8', $fila->detalle,
            'El RUT del empleado no debe aparecer en el detalle de lectura.');
    }

    public function test_lectura_liquidacion_con_flag_desactivada_no_escribe_fila(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        [, , $liquidacion] = $this->crearLiquidacionMinima($empresa->id, '99.999.999-9', 2026, 5);

        // Desactivar flag
        config(['auditoria.lectura_pii' => false]);

        $response = $this->actingAs($admin)->getJson("/api/rrhh/liquidaciones/{$liquidacion->id}");
        $response->assertStatus(200);

        $count = Auditoria::where('auditable_type', Liquidacion::class)
            ->where('auditable_id', $liquidacion->id)
            ->where('operacion', 'LECTURA')
            ->count();

        $this->assertEquals(0, $count,
            'Con la flag desactivada, no debe escribirse ninguna fila de auditoria LECTURA.');
    }
}
