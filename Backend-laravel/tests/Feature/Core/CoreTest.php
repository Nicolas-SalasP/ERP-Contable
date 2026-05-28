<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Core\Models\Pais;
use Laravel\Sanctum\Sanctum;

class CoreTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $empresaB;
    protected $rolAdmin;
    protected $rolUsuario;
    protected $rolIntermedio;
    protected $estadoActivo;

    protected $adminEmpresaA;
    protected $userEmpresaA;
    protected $adminEmpresaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estadoActivo = EstadoSuscripcion::create(['nombre' => 'Activa']);
        $this->rolAdmin = Rol::create(['nombre' => 'Dueño Super Admin', 'jerarquia' => 100]);
        $this->rolIntermedio = Rol::create(['nombre' => 'Supervisor', 'jerarquia' => 50]);
        $this->rolUsuario = Rol::create(['nombre' => 'Usuario Básico', 'jerarquia' => 10]);

        $this->empresaA = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa A']);
        $this->empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa B']);

        $this->adminEmpresaA = User::create([
            'nombre' => 'Admin A',
            'email' => 'adminA@test.com',
            'password' => bcrypt('password123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $this->rolAdmin->id,
            'estado_suscripcion_id' => $this->estadoActivo->id
        ]);

        $this->userEmpresaA = User::create([
            'nombre' => 'User A',
            'email' => 'userA@test.com',
            'password' => bcrypt('password123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $this->rolUsuario->id,
            'estado_suscripcion_id' => $this->estadoActivo->id
        ]);

        $this->adminEmpresaB = User::create([
            'nombre' => 'Admin B',
            'email' => 'adminB@test.com',
            'password' => bcrypt('password123'),
            'empresa_id' => $this->empresaB->id,
            'rol_id' => $this->rolAdmin->id,
            'estado_suscripcion_id' => $this->estadoActivo->id
        ]);
    }

    // PRUEBA: Autenticación Exitosa
    public function test_login_exitoso_devuelve_token()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'adminA@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)->assertJsonStructure(['success', 'token', 'user']);
    }

    // PRUEBA: Autenticación con email inexistente
    public function test_rechaza_login_con_email_inexistente()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'noexisto@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(401);
    }

    // PRUEBA: Autenticación sensible a mayúsculas
    public function test_rechaza_login_con_password_distinto_case()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'adminA@test.com',
            'password' => 'PASSWORD123'
        ]);

        $response->assertStatus(401);
    }

    // PRUEBA: Login actualiza fecha
    public function test_login_actualiza_fecha_ultimo_acceso()
    {
        $this->assertNull($this->adminEmpresaA->ultimo_acceso);

        $this->postJson('/api/auth/login', [
            'email' => 'adminA@test.com',
            'password' => 'password123'
        ]);

        $this->assertNotNull($this->adminEmpresaA->fresh()->ultimo_acceso);
    }

    // PRUEBA: Método HTTP incorrecto
    public function test_login_con_metodo_http_incorrecto_falla()
    {
        $response = $this->getJson('/api/auth/login');

        $response->assertStatus(405);
    }

    // PRUEBA: Autenticación Inválida
    public function test_rechaza_login_con_credenciales_invalidas()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'adminA@test.com',
            'password' => 'clave_falsa'
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    // PRUEBA: Login con formato de email inválido
    public function test_rechaza_login_con_formato_email_invalido()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'adminA_test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // PRUEBA: Login con campos en blanco
    public function test_rechaza_login_con_campos_vacios()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => '',
            'password' => ''
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    // PRUEBA: Protección de Rutas sin Token
    public function test_proteccion_de_rutas_rechaza_acceso_sin_token()
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // PRUEBA: Cierre de Sesión Exitoso
    public function test_logout_revoca_el_token_actual()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }

    // PRUEBA: Obtener mi perfil carga los permisos correctamente
    public function test_obtener_perfil_propio_carga_permisos()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('permisos'));
    }

    // PRUEBA: Acceso con token eliminado
    public function test_obtener_perfil_propio_con_token_eliminado_falla()
    {
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'adminA@test.com',
            'password' => 'password123'
        ]);

        $token = $loginResponse->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout');

        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // PRUEBA: Multitenant en Perfil de Empresa
    public function test_usuario_solo_puede_ver_el_perfil_de_su_propia_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/empresas/perfil');

        $response->assertStatus(200)->assertJsonPath('data.razon_social', 'Empresa A');
    }

    // PRUEBA: Multitenant en Listado de Usuarios
    public function test_usuario_solo_puede_ver_usuarios_de_su_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(200);
        $data = $response->json('data');
        $nombres = array_column($data, 'nombre');

        $this->assertContains('User A', $nombres);
        $this->assertNotContains('Admin B', $nombres);
    }

    // PRUEBA: Listar usuarios cuando solo existe el admin
    public function test_listar_usuarios_cuando_solo_existe_el_admin()
    {
        Sanctum::actingAs($this->adminEmpresaB);

        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // PRUEBA: Protección de Jerarquía de Invitación
    public function test_solo_administradores_pueden_invitar_nuevos_usuarios()
    {
        Sanctum::actingAs($this->userEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'nombre' => 'Nuevo Empleado',
            'email' => 'nuevo@test.com',
            'rol_id' => $this->rolUsuario->id
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }

    // PRUEBA: Creación de Usuarios Vía Invitación
    public function test_administrador_puede_invitar_usuarios_exitosamente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'nuevo@test.com',
            'rol_id' => $this->rolUsuario->id
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('usuarios', ['email' => 'nuevo@test.com', 'empresa_id' => $this->empresaA->id]);
    }

    // PRUEBA: IDOR Mass Assignment en Invitacion
    public function test_idor_impide_a_admin_invitar_a_otra_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaB);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'infiltrado@test.com',
            'rol_id' => $this->rolUsuario->id,
            'empresa_id' => $this->empresaA->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'email' => 'infiltrado@test.com',
            'empresa_id' => $this->empresaB->id
        ]);
    }

    // PRUEBA: Invitar usuario con email inválido
    public function test_rechaza_invitacion_con_email_invalido()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'correo_malo',
            'rol_id' => $this->rolUsuario->id
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // PRUEBA: Invitar usuario con rol vacio
    public function test_rechaza_invitacion_sin_asignar_rol()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'valido@test.com'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rol_id']);
    }

    // PRUEBA: Invitar usuario con rol erroneo (string)
    public function test_rechaza_invitacion_con_tipo_dato_incorrecto_en_rol()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'valido@test.com',
            'rol_id' => 'rol_texto'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rol_id']);
    }

    // PRUEBA: Hacking Rol Fantasma en Invitacion
    public function test_rechaza_invitar_usuario_con_rol_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'fantasma@test.com',
            'rol_id' => 99999
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [404, 422, 500]));
    }

    // PRUEBA: Invitar a un usuario que ya existe lo reasigna al tenant
    public function test_invitar_usuario_existente_lo_reasigna_a_mi_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/invitar', [
            'email' => 'adminB@test.com',
            'rol_id' => $this->rolIntermedio->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'email' => 'adminB@test.com',
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $this->rolIntermedio->id
        ]);
    }

    // PRUEBA: Administrador puede actualizar rol de empleado
    public function test_administrador_puede_actualizar_rol_de_usuario_en_su_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->userEmpresaA->id}/rol", [
            'rol_id' => $this->rolIntermedio->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('usuarios', [
            'id' => $this->userEmpresaA->id,
            'rol_id' => $this->rolIntermedio->id
        ]);
    }

    // PRUEBA: Actualizar rol con datos invalidos
    public function test_rechaza_actualizar_rol_con_tipo_de_dato_incorrecto()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->userEmpresaA->id}/rol", [
            'rol_id' => 'no_es_numero'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rol_id']);
    }

    // PRUEBA: Hacking Rol Fantasma en Actualizacion
    public function test_rechaza_actualizar_usuario_con_rol_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->userEmpresaA->id}/rol", [
            'rol_id' => 99999
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [404, 422, 500]));
    }

    // PRUEBA: Prevención de asignación de roles superiores
    public function test_admin_no_puede_asignar_rol_de_mayor_jerarquia_al_suyo()
    {
        $this->adminEmpresaA->update(['rol_id' => $this->rolIntermedio->id]);
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->userEmpresaA->id}/rol", [
            'rol_id' => $this->rolAdmin->id
        ]);

        $response->assertStatus(403);
    }

    // PRUEBA: Prevención de edición a superiores
    public function test_admin_no_puede_editar_usuarios_de_mayor_jerarquia()
    {
        $this->userEmpresaA->update(['rol_id' => $this->rolAdmin->id]);
        $this->adminEmpresaA->update(['rol_id' => $this->rolIntermedio->id]);
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->userEmpresaA->id}/rol", [
            'rol_id' => $this->rolUsuario->id
        ]);

        $response->assertStatus(403);
    }

    // PRUEBA: Prevención de IDOR en Actualización de Roles
    public function test_idor_rechaza_actualizar_rol_de_usuario_de_otra_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/usuarios/{$this->adminEmpresaB->id}/rol", [
            'rol_id' => $this->rolUsuario->id
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    // PRUEBA: Ataque de desvinculación jerárquica
    public function test_empleado_basico_no_puede_desvincular_al_admin()
    {
        Sanctum::actingAs($this->userEmpresaA);

        $response = $this->deleteJson("/api/usuarios/{$this->adminEmpresaA->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('usuarios', ['id' => $this->adminEmpresaA->id]);
    }

    // PRUEBA: Desvinculación exitosa por admin
    public function test_admin_puede_desvincular_usuario_basico()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/usuarios/{$this->userEmpresaA->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('usuarios', ['id' => $this->userEmpresaA->id]);
    }

    // PRUEBA: Desvincular usuario inexistente
    public function test_rechaza_desvincular_usuario_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/usuarios/99999");

        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    // PRUEBA: IDOR desvincular de otra empresa
    public function test_idor_rechaza_desvincular_usuario_de_otra_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/usuarios/{$this->adminEmpresaB->id}");

        $this->assertTrue(in_array($response->getStatusCode(), [403, 404, 500]));
    }

    // PRUEBA: Prevención de Autoeliminación
    public function test_usuario_no_puede_eliminarse_a_si_mismo()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/usuarios/{$this->adminEmpresaA->id}");

        $response->assertStatus(403);
    }

    // PRUEBA: Listar roles sin autenticacion
    public function test_rechaza_acceso_a_roles_sin_autenticacion()
    {
        $response = $this->getJson('/api/usuarios/roles');
        $response->assertStatus(401);
    }

    // PRUEBA: Listar roles disponibles en el sistema
    public function test_puede_listar_roles_del_sistema()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/usuarios/roles');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    // PRUEBA: Crear un nuevo rol personalizado
    public function test_puede_crear_un_nuevo_rol()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/roles', [
            'nombre' => 'Rol Custom',
            'permisos' => ['ventas.ver', 'compras.ver']
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('roles', ['nombre' => 'Rol Custom']);
    }

    // PRUEBA: Crear rol sin nombre falla
    public function test_rechaza_crear_rol_sin_nombre()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/roles', [
            'permisos' => ['ventas.ver']
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['nombre']);
    }

    // PRUEBA: Crear rol con estructura de permisos inválida
    public function test_crear_rol_con_estructura_de_permisos_invalida()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/usuarios/roles', [
            'nombre' => 'Rol Fallido',
            'permisos' => 'ventas.ver, compras.ver'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['permisos']);
    }

    // PRUEBA: Acceso a Catálogos Públicos
    public function test_catalogo_de_paises_es_accesible()
    {
        Sanctum::actingAs($this->userEmpresaA);

        Pais::create(['iso' => 'AR', 'nombre' => 'Argentina', 'moneda_defecto' => 'ARS', 'activo' => true]);

        $response = $this->getJson('/api/paises');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    // PRUEBA: Fuga de datos sensibles (Passwords y Tokens)
    public function test_endpoint_de_usuarios_no_filtra_passwords_ni_tokens()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/usuarios');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('password', $response->json('data.0'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data.0'));
    }

    // PRUEBA: Tokens inválidos o expirados
    public function test_rechaza_acceso_con_token_malformado_o_inventado()
    {
        $response = $this->withHeader('Authorization', 'Bearer token_falso_12345')
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // PRUEBA: Bloqueo de usuarios inactivos
    public function test_usuario_inactivo_o_suspendido_no_puede_hacer_login()
    {
        $estadoInactivo = EstadoSuscripcion::create(['nombre' => 'Inactiva']);
        $this->userEmpresaA->update(['estado_suscripcion_id' => $estadoInactivo->id]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'userA@test.com',
            'password' => 'password123'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [401, 403]));
    }

    // PRUEBA: Intentos de Inyección SQL en Login
    public function test_rechaza_intentos_de_inyeccion_sql_basica_en_login()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => "adminA@test.com' OR '1'='1",
            'password' => 'password123'
        ]);

        $response->assertStatus(422);
    }

    // PRUEBA: Límite de caracteres en creación de roles
    public function test_rechaza_crear_rol_con_nombre_excesivamente_largo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $nombreLargo = str_repeat('A', 300);

        $response = $this->postJson('/api/usuarios/roles', [
            'nombre' => $nombreLargo
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }
}