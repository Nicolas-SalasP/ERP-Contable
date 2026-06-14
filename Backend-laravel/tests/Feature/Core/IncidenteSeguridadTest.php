<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\IncidenteSeguridad;

/**
 * Fase 6 — Registro de incidentes de seguridad (Ley 21.663 / 21.719).
 *
 * Cubre:
 *  1. Admin crea un incidente → 201 con registrado_por, empresa_id y estado ABIERTO.
 *  2. Aislamiento multitenant: admin de empresa A no ve incidentes de empresa B.
 *  3. Admin actualiza línea de tiempo → reporte_csirt_at y estado=CONTENIDO.
 *  4. Usuario sin permiso (jerarquía baja) recibe 403 al intentar crear.
 *  5. Severidad inválida es rechazada con 422.
 */
class IncidenteSeguridadTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // -------------------------------------------------------------------------
    // Test 1 — Creación exitosa
    // -------------------------------------------------------------------------

    public function test_admin_crea_incidente_con_datos_correctos(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/incidentes', [
            'titulo'       => 'Acceso no autorizado a BBDD de clientes',
            'descripcion'  => 'Se detectó un acceso no autorizado al servidor de producción.',
            'severidad'    => IncidenteSeguridad::SEVERIDAD_ALTA,
            'detectado_at' => '2026-06-14 10:00:00',
        ]);

        $response->assertStatus(201);

        $data = $response->json('data');

        $this->assertEquals($empresa->id, $data['empresa_id']);
        $this->assertEquals($admin->nombre, $data['registrado_por']);
        $this->assertEquals(IncidenteSeguridad::ESTADO_ABIERTO, $data['estado']);
        $this->assertEquals(IncidenteSeguridad::SEVERIDAD_ALTA, $data['severidad']);
    }

    // -------------------------------------------------------------------------
    // Test 2 — Aislamiento multitenant en listado
    // -------------------------------------------------------------------------

    public function test_admin_solo_ve_incidentes_de_su_empresa(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        [$empresaB, $adminB] = $this->crearEmpresaConAdmin();

        // Crear un incidente en empresa B (sin Sanctum, para evitar que EmpresaScope
        // filtre por empresa A durante la inserción).
        IncidenteSeguridad::withoutGlobalScopes()->create([
            'empresa_id'    => $empresaB->id,
            'titulo'        => 'Incidente de empresa B',
            'descripcion'   => 'Descripción del incidente de B.',
            'severidad'     => IncidenteSeguridad::SEVERIDAD_BAJA,
            'detectado_at'  => now(),
            'estado'        => IncidenteSeguridad::ESTADO_ABIERTO,
            'registrado_por' => $adminB->nombre,
        ]);

        // Autenticar como admin de empresa A
        Sanctum::actingAs($adminA);

        $response = $this->getJson('/api/incidentes');
        $response->assertStatus(200);

        $items = $response->json('data.data');
        $this->assertIsArray($items);

        // Admin A no debe ver ningún incidente de empresa B
        foreach ($items as $item) {
            $this->assertEquals($empresaA->id, $item['empresa_id'],
                'El listado expone un incidente de otra empresa.');
        }

        // El incidente de empresa B no debe aparecer
        $titulos = array_column($items, 'titulo');
        $this->assertNotContains('Incidente de empresa B', $titulos);
    }

    // -------------------------------------------------------------------------
    // Test 3 — Actualización de línea de tiempo
    // -------------------------------------------------------------------------

    public function test_admin_actualiza_reporte_csirt_y_estado_contenido(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        // Crear incidente directamente en BD (sin scope)
        $incidente = IncidenteSeguridad::withoutGlobalScopes()->create([
            'empresa_id'     => $empresa->id,
            'titulo'         => 'Brecha de datos',
            'descripcion'    => 'Datos de clientes expuestos.',
            'severidad'      => IncidenteSeguridad::SEVERIDAD_CRITICA,
            'detectado_at'   => now(),
            'estado'         => IncidenteSeguridad::ESTADO_ABIERTO,
            'registrado_por' => $admin->nombre,
        ]);

        Sanctum::actingAs($admin);

        $csirtAt = '2026-06-14 13:00:00';

        $response = $this->putJson("/api/incidentes/{$incidente->id}", [
            'reporte_csirt_at' => $csirtAt,
            'estado'           => IncidenteSeguridad::ESTADO_CONTENIDO,
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(IncidenteSeguridad::ESTADO_CONTENIDO, $data['estado']);
        $this->assertNotNull($data['reporte_csirt_at']);
    }

    // -------------------------------------------------------------------------
    // Test 4 — No-admin recibe 403
    // -------------------------------------------------------------------------

    public function test_usuario_sin_permiso_recibe_403_al_crear_incidente(): void
    {
        [$empresa, $usuarioBasico] = $this->crearEmpresaConAdmin(
            [],
            ['rol_id' => $this->rolUsuarioBasico->id]
        );

        Sanctum::actingAs($usuarioBasico);

        $response = $this->postJson('/api/incidentes', [
            'titulo'       => 'Intento no autorizado',
            'descripcion'  => 'Sin permisos.',
            'severidad'    => IncidenteSeguridad::SEVERIDAD_BAJA,
            'detectado_at' => '2026-06-14 10:00:00',
        ]);

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Test 5 — Severidad inválida devuelve 422
    // -------------------------------------------------------------------------

    public function test_severidad_invalida_es_rechazada_con_422(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/incidentes', [
            'titulo'       => 'Incidente con severidad inválida',
            'descripcion'  => 'Test de validación.',
            'severidad'    => 'MUY_GRAVE',   // no válida
            'detectado_at' => '2026-06-14 10:00:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('severidad');
    }
}
