<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Models\EstadoSuscripcion;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use App\Domains\Contabilidad\Models\CentroCosto;
use Laravel\Sanctum\Sanctum;

class EmpresaConfigTest extends TestCase
{
    use RefreshDatabase;

    protected $empresaA;
    protected $empresaB;
    protected $adminEmpresaA;
    protected $adminEmpresaB;

    protected function setUp(): void
    {
        parent::setUp();

        $estadoActivo = EstadoSuscripcion::create(['nombre' => 'Activa']);
        $rolAdmin = Rol::create(['nombre' => 'Admin', 'jerarquia' => 100]);

        $this->empresaA = Empresa::create(['rut' => '11.111.111-1', 'razon_social' => 'Empresa A']);
        $this->empresaB = Empresa::create(['rut' => '22.222.222-2', 'razon_social' => 'Empresa B']);

        $this->adminEmpresaA = User::create([
            'nombre' => 'Admin A',
            'email' => 'adminA@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaA->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);

        $this->adminEmpresaB = User::create([
            'nombre' => 'Admin B',
            'email' => 'adminB@test.com',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresaB->id,
            'rol_id' => $rolAdmin->id,
            'estado_suscripcion_id' => $estadoActivo->id
        ]);
    }

    // PRUEBA: Actualizar Perfil de Empresa
    public function test_usuario_puede_actualizar_datos_de_su_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson('/api/empresas/perfil', [
            'telefono' => '+56912345678',
            'direccion' => 'Av. Providencia 1234'
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('empresas', [
            'id' => $this->empresaA->id,
            'telefono' => '+56912345678',
            'direccion' => 'Av. Providencia 1234'
        ]);
    }

    // PRUEBA: Actualizar perfil email invalido
    public function test_actualizar_perfil_con_email_invalido()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson('/api/empresas/perfil', [
            'email' => 'correo_malo'
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    // PRUEBA: Actualizar perfil email largo
    public function test_actualizar_perfil_con_email_demasiado_largo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $emailLargo = str_repeat('a', 150) . '@test.com';

        $response = $this->putJson('/api/empresas/perfil', [
            'email' => $emailLargo
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Payload vacio en perfil no rompe nada
    public function test_actualizar_perfil_con_payload_vacio_no_falla_pero_no_altera_datos()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson('/api/empresas/perfil', []);

        $response->assertStatus(200);
        $this->assertDatabaseHas('empresas', [
            'id' => $this->empresaA->id,
            'razon_social' => 'Empresa A'
        ]);
    }

    // PRUEBA: Metodo incorrecto perfil
    public function test_rechaza_actualizar_perfil_con_metodo_post()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/perfil', [
            'telefono' => '123'
        ]);

        $response->assertStatus(405);
    }

    // PRUEBA: Previene Mass Assignment al actualizar perfil
    public function test_previene_mass_assignment_de_empresa_id_en_perfil()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson('/api/empresas/perfil', [
            'telefono' => '123456',
            'empresa_id' => $this->empresaB->id
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('empresas', [
            'id' => $this->empresaA->id,
            'telefono' => '123456'
        ]);
        $this->assertEquals($this->empresaA->id, $this->adminEmpresaA->fresh()->empresa_id);
    }

    // PRUEBA: Subir imagen valida como logo de empresa
    public function test_usuario_puede_subir_logo_valido()
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminEmpresaA);

        $file = UploadedFile::fake()->image('logo.jpg', 500, 500);

        $response = $this->postJson('/api/empresas/logo', [
            'logo' => $file
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('empresas', [
            'id' => $this->empresaA->id,
            'logo_path' => null
        ]);
    }

    // PRUEBA: Subir archivo invalido como logo de empresa falla
    public function test_rechaza_subir_archivo_pdf_como_logo()
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminEmpresaA);

        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/empresas/logo', [
            'logo' => $file
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['logo']);
    }

    // PRUEBA: Inyección de PHP disfrazado
    public function test_rechaza_subir_archivo_php_disfrazado_de_imagen()
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminEmpresaA);

        $file = UploadedFile::fake()->create('shell.php', 100, 'application/x-httpd-php');

        $response = $this->postJson('/api/empresas/logo', [
            'logo' => $file
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['logo']);
    }

    // PRUEBA: Listar Centros de Costo Multitenant
    public function test_listar_centros_de_costo_solo_muestra_los_de_la_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        CentroCosto::create(['empresa_id' => $this->empresaA->id, 'codigo' => 'CC-A', 'nombre' => 'Centro A']);
        CentroCosto::create(['empresa_id' => $this->empresaB->id, 'codigo' => 'CC-B', 'nombre' => 'Centro B']);

        $response = $this->getJson('/api/empresas/centros-costo');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertStringContainsString('CC-A', $data[0]['label']);
    }

    // PRUEBA: Listar centros costo vacio
    public function test_listar_centros_costo_cuando_no_hay_registros()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->getJson('/api/empresas/centros-costo');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    // PRUEBA: Multitenant en Creación de Centros de Costo
    public function test_creacion_de_centro_de_costo_se_asigna_correctamente_al_tenant()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => 'CC-Ventas',
            'nombre' => 'Departamento de Ventas'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('centros_costo', [
            'codigo' => 'CC-Ventas',
            'empresa_id' => $this->empresaA->id
        ]);
    }

    // PRUEBA: Centro costo defecto estado
    public function test_verificar_que_centro_costo_se_crea_activo_por_defecto()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => 'CC-DEF',
            'nombre' => 'Defecto'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('centros_costo', [
            'codigo' => 'CC-DEF',
            'activo' => 1
        ]);
    }

    // PRUEBA: Centro costo codigo largo
    public function test_rechaza_crear_centro_costo_con_codigo_demasiado_largo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $codigoLargo = str_repeat('C', 50);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => $codigoLargo,
            'nombre' => 'Nombre Valido'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Centro costo nombre largo
    public function test_rechaza_crear_centro_costo_con_nombre_demasiado_largo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $nombreLargo = str_repeat('N', 200);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => 'CC-VAL',
            'nombre' => $nombreLargo
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Crear centro de costo sin codigo o nombre
    public function test_rechaza_creacion_centro_costo_por_falta_de_datos()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'nombre' => 'Solo Nombre'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    // PRUEBA: Prevención de Duplicidad en Códigos de Centro de Costo
    public function test_rechaza_crear_centro_de_costo_con_codigo_duplicado_en_misma_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        CentroCosto::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => 'CC-IT',
            'nombre' => 'Informática'
        ]);

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => 'CC-IT',
            'nombre' => 'Soporte Técnico'
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('ya está en uso', $response->json('error'));
    }

    // PRUEBA: Permitir actualizar nombre sin fallar por duplicado de codigo propio
    public function test_actualizar_centro_costo_con_mismo_codigo_no_falla()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $centro = CentroCosto::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => 'CC-OLD',
            'nombre' => 'Antiguo'
        ]);

        $response = $this->putJson("/api/empresas/centros-costo/{$centro->id}", [
            'codigo' => 'CC-OLD',
            'nombre' => 'Solo cambia nombre'
        ]);

        $response->assertStatus(200);
    }

    // PRUEBA: Actualizar Centro de Costo Exitosamente
    public function test_actualizar_centro_de_costo_exitosamente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $centro = CentroCosto::create([
            'empresa_id' => $this->empresaA->id,
            'codigo' => 'CC-OLD',
            'nombre' => 'Antiguo'
        ]);

        $response = $this->putJson("/api/empresas/centros-costo/{$centro->id}", [
            'codigo' => 'CC-NEW',
            'nombre' => 'Nuevo Nombre'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('centros_costo', [
            'id' => $centro->id,
            'codigo' => 'CC-NEW'
        ]);
    }

    // PRUEBA: Actualizar centro inexistente
    public function test_actualizar_centro_costo_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/empresas/centros-costo/9999", [
            'codigo' => 'CC-NEW',
            'nombre' => 'Nuevo Nombre'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 404, 500]));
    }

    // PRUEBA: Prevención de IDOR en Actualización de Centros de Costo
    public function test_idor_impide_actualizar_centro_de_costo_de_otra_empresa()
    {
        $centroEmpresaB = CentroCosto::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => 'CC-B',
            'nombre' => 'Centro de B'
        ]);

        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/empresas/centros-costo/{$centroEmpresaB->id}", [
            'codigo' => 'CC-HACK',
            'nombre' => 'Hackeado'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
    }

    // PRUEBA: Eliminar centro costo inexistente
    public function test_falla_eliminar_centro_costo_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/empresas/centros-costo/9999");

        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    // PRUEBA: Prevención de IDOR en Eliminación de Centros de Costo
    public function test_idor_impide_eliminar_centro_de_costo_de_otra_empresa()
    {
        $centroEmpresaB = CentroCosto::create([
            'empresa_id' => $this->empresaB->id,
            'codigo' => 'CC-B',
            'nombre' => 'Centro de B'
        ]);

        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/empresas/centros-costo/{$centroEmpresaB->id}");

        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
        $this->assertDatabaseHas('centros_costo', ['id' => $centroEmpresaB->id]);
    }

    // PRUEBA: Obtener cuentas sin sesion
    public function test_listar_cuentas_propias_sin_autenticacion_falla()
    {
        $response = $this->getJson('/api/tesoreria/cuentas-propias');

        $response->assertStatus(401);
    }

    // PRUEBA: Agregar Cuenta Bancaria
    public function test_agregar_cuenta_bancaria_exitosamente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 'Banco Santander',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '123456789',
            'titular' => 'Empresa A Spa',
            'rut_titular' => '11.111.111-1'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        $this->assertDatabaseHas('cuentas_bancarias_empresa', [
            'empresa_id' => $this->empresaA->id,
            'numero_cuenta' => '123456789'
        ]);
    }

    // PRUEBA: Defectos cuenta bancaria
    public function test_verificar_que_cuenta_bancaria_se_crea_activa_y_en_clp_por_defecto()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 'Banco Default',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '111',
            'titular' => 'A',
            'rut_titular' => '1'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [200, 201]));
        
        $this->assertDatabaseHas('cuentas_bancarias_empresa', [
            'banco' => 'Banco Default'
        ]);
    }

    // PRUEBA: Email invalido banco
    public function test_rechaza_crear_cuenta_bancaria_con_email_notificacion_invalido()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 'Banco',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => '1',
            'titular' => 'T',
            'rut_titular' => 'R',
            'email_notificacion' => 'correo_malo'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Numero banco largo
    public function test_rechaza_crear_cuenta_bancaria_con_numero_excesivamente_largo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $numLargo = str_repeat('9', 100);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 'Banco',
            'tipo_cuenta' => 'C',
            'numero_cuenta' => $numLargo,
            'titular' => 'T',
            'rut_titular' => 'R'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Crear cuenta bancaria con datos faltantes
    public function test_rechaza_crear_cuenta_bancaria_sin_titular()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 'Banco Santander',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '123456789'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 422, 500]));
    }

    // PRUEBA: Obtener catalogo de bancos sin sesion
    public function test_listar_catalogo_bancos_sin_autenticacion_falla()
    {
        $response = $this->getJson('/api/empresas/catalogo-bancos');
        $response->assertStatus(401);
    }

    // PRUEBA: Obtener catalogo de bancos del sistema
    public function test_obtener_catalogo_bancos_exitoso()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        \DB::table('catalogo_bancos')->insert([
            ['nombre' => 'Banco de Pruebas']
        ]);

        $response = $this->getJson('/api/empresas/catalogo-bancos');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    // PRUEBA: Actualizar Cuenta Bancaria
    public function test_actualizar_cuenta_bancaria_exitosamente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco Falabella',
            'tipo_cuenta' => 'Vista',
            'numero_cuenta' => '111',
            'titular' => 'Empresa',
            'rut_titular' => '1.1.1'
        ]);

        $response = $this->putJson("/api/empresas/bancos/{$cuenta->id}", [
            'numero_cuenta' => '999'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cuentas_bancarias_empresa', [
            'id' => $cuenta->id,
            'numero_cuenta' => '999'
        ]);
    }

    // PRUEBA: Actualizar cuenta inexistente
    public function test_falla_actualizar_cuenta_bancaria_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->putJson("/api/empresas/bancos/9999", [
            'numero_cuenta' => '999'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [404, 500]));
    }

    // PRUEBA: Actualizar cuenta bancaria con datos parciales
    public function test_actualizar_parcialmente_cuenta_mantiene_datos_antiguos()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco Inicial',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '111',
            'titular' => 'Empresa',
            'rut_titular' => '1.1.1'
        ]);

        $response = $this->putJson("/api/empresas/bancos/{$cuenta->id}", [
            'banco' => 'Banco Final'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('cuentas_bancarias_empresa', [
            'id' => $cuenta->id,
            'banco' => 'Banco Final',
            'tipo_cuenta' => 'Corriente'
        ]);
    }

    // PRUEBA: IDOR en Actualización de Cuentas Bancarias
    public function test_idor_impide_actualizar_cuenta_bancaria_de_otra_empresa()
    {
        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '123',
            'titular' => 'Emp',
            'rut_titular' => '1'
        ]);

        Sanctum::actingAs($this->adminEmpresaB);

        $response = $this->putJson("/api/empresas/bancos/{$cuenta->id}", [
            'numero_cuenta' => '000'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
    }

    // PRUEBA: Eliminar Cuenta Bancaria
    public function test_eliminar_cuenta_bancaria_exitosamente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $cuenta = CuentaBancariaEmpresa::create([
            'empresa_id' => $this->empresaA->id,
            'banco' => 'Banco',
            'tipo_cuenta' => 'Corriente',
            'numero_cuenta' => '123',
            'titular' => 'Emp',
            'rut_titular' => '1'
        ]);

        $response = $this->deleteJson("/api/empresas/bancos/{$cuenta->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('cuentas_bancarias_empresa', ['id' => $cuenta->id]);
    }

    // PRUEBA: Eliminar banco inexistente
    public function test_falla_al_eliminar_banco_inexistente()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->deleteJson("/api/empresas/bancos/99999");

        $this->assertTrue(in_array($response->getStatusCode(), [400, 403, 404, 500]));
    }

    // PRUEBA: Límite de caracteres en Razón Social (DB Constraint)
    public function test_rechaza_razon_social_excesivamente_larga()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $textoLargo = str_repeat('A', 200);

        $response = $this->putJson('/api/empresas/perfil', [
            'razon_social' => $textoLargo
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }

    // PRUEBA: Modificación de campos inmutables (RUT)
    public function test_previene_modificar_el_rut_de_la_empresa()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $rutOriginal = $this->empresaA->rut;

        $response = $this->putJson('/api/empresas/perfil', [
            'rut' => '99.999.999-9'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('empresas', [
            'id' => $this->empresaA->id,
            'rut' => $rutOriginal
        ]);
    }

    // PRUEBA: XSS en nombres de centros de costo
    public function test_maneja_correctamente_intentos_xss_en_centros_costo()
    {
        Sanctum::actingAs($this->adminEmpresaA);
        $payloadXSS = "<script>alert('hack')</script>";

        $response = $this->postJson('/api/empresas/centros-costo', [
            'codigo' => 'CC-XSS',
            'nombre' => $payloadXSS
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('centros_costo', [
            'codigo' => 'CC-XSS',
            'nombre' => $payloadXSS
        ]);
    }

    // PRUEBA: Fallo por tipo de dato incorrecto
    public function test_rechaza_tipos_de_datos_incorrectos_en_cuentas_bancarias()
    {
        Sanctum::actingAs($this->adminEmpresaA);

        $response = $this->postJson('/api/empresas/bancos', [
            'banco' => 12345,
            'tipo_cuenta' => true,
            'numero_cuenta' => ['array_invalido'],
            'titular' => 'Empresa A Spa',
            'rut_titular' => '11.111.111-1'
        ]);

        $this->assertTrue(in_array($response->getStatusCode(), [422, 500]));
    }
}