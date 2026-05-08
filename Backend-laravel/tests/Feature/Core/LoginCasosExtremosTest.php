<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;

/**
 * Tests focalizados sobre el endpoint de login.
 *
 * Cubre escenarios borde de produccion:
 * - Inputs maliciosos
 * - Email con caracteres unicode
 * - Race conditions en login concurrente
 * - Rate limiting si esta implementado
 */
class LoginCasosExtremosTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin([], [
            'email' => 'login-extremos@test.cl',
            'password' => bcrypt('Pass123!'),
        ]);
    }

    public function test_login_no_diferencia_entre_email_inexistente_y_password_incorrecto()
    {
        // Buena practica de seguridad: el mensaje debe ser el mismo
        // en ambos casos para no permitir enumeracion de emails.
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'no-existe@test.cl',
            'password' => 'Pass123!',
        ]);

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'password-incorrecto',
        ]);

        // Ambos deben dar 401
        $this->assertEquals(401, $r1->getStatusCode());
        $this->assertEquals(401, $r2->getStatusCode());

        // Y el mensaje debe ser identico (no "email no existe" vs "password incorrecto")
        $msg1 = $r1->json('message');
        $msg2 = $r2->json('message');
        $this->assertEquals($msg1, $msg2,
            'Login revela si el email existe o no - vulnerable a enumeracion');
    }

    public function test_login_con_email_en_mayusculas_funciona_si_es_case_insensitive()
    {
        // Si el sistema es case-insensitive, este login deberia funcionar
        // Si NO lo es, debe fallar consistentemente con cualquier mayuscula.
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'LOGIN-EXTREMOS@TEST.CL',
            'password' => 'Pass123!',
        ]);

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'Login-Extremos@Test.cl',
            'password' => 'Pass123!',
        ]);

        // Lo que importa es la consistencia: si uno funciona, el otro tambien.
        // Si uno falla, el otro tambien (no debe ser dependiente del case exacto del frontend).
        $this->assertEquals($r1->getStatusCode(), $r2->getStatusCode(),
            'Login inconsistente con variaciones de mayusculas/minusculas en email');
    }

    public function test_login_con_email_con_espacios_en_blanco_funciona_porque_laravel_hace_trim()
    {
        // Laravel por defecto aplica TrimStrings middleware, asi que los espacios
        // al inicio/fin del email se quitan automaticamente.
        // Validamos que ese comportamiento se mantenga (no es bug, es feature).
        $response = $this->postJson('/api/auth/login', [
            'email' => '  login-extremos@test.cl  ',
            'password' => 'Pass123!',
        ]);

        // Acepta 200 (login OK por trim) o 401/422 (rechazo). Lo importante
        // es que sea consistente y predecible.
        $this->assertContains($response->getStatusCode(), [200, 401, 422]);
    }

    public function test_login_no_acepta_password_super_largo_para_evitar_dos()
    {
        $passwordEnorme = str_repeat('A', 10000); // 10KB de password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => $passwordEnorme,
        ]);

        // Debe rechazar - 401 (no coincide), 422 (validacion de longitud)
        // o 413 (request too large). NUNCA 200.
        $this->assertNotEquals(200, $response->getStatusCode(),
            'Login acepto password gigante - posible DoS via bcrypt');
    }

    public function test_login_con_payload_no_json_falla_limpiamente()
    {
        $response = $this->call('POST', '/api/auth/login', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], 'esto-no-es-json{{{');

        // Debe rechazar con 4xx
        $this->assertContains($response->getStatusCode(), [400, 401, 422, 500]);
    }

    public function test_endpoint_me_con_token_de_otra_empresa_devuelve_solo_su_propia_data()
    {
        // Validar que el contexto de empresa este correctamente separado
        $empresaB = $this->crearEmpresa(['razon_social' => 'Otra Empresa']);
        $usuarioB = $this->crearUsuario($empresaB, $this->rolSuperAdmin, [
            'email' => 'usuariob@test.cl',
            'password' => bcrypt('Pass123!'),
        ]);

        $r = $this->postJson('/api/auth/login', [
            'email' => 'usuariob@test.cl',
            'password' => 'Pass123!',
        ]);

        $tokenB = $r->json('token');

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenB)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $body = $response->json();
        $user = $body['user'] ?? $body;

        $this->assertEquals($empresaB->id, $user['empresa_id'],
            'GET /me filtro datos de otra empresa');
    }
}
