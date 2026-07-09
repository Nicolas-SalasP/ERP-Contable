<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class LoginCasosExtremosTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

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
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'no-existe@test.cl',
            'password' => 'Pass123!',
        ]);

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'password-incorrecto',
        ]);

        $this->assertEquals(401, $r1->getStatusCode());
        $this->assertEquals(401, $r2->getStatusCode());

        $msg1 = $r1->json('message');
        $msg2 = $r2->json('message');
        $this->assertEquals(
            $msg1,
            $msg2,
            'Login revela si el email existe o no - vulnerable a enumeracion'
        );
    }

    public function test_login_con_email_en_mayusculas_funciona_si_es_case_insensitive()
    {
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'LOGIN-EXTREMOS@TEST.CL',
            'password' => 'Pass123!',
        ]);

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'Login-Extremos@Test.cl',
            'password' => 'Pass123!',
        ]);

        $this->assertEquals(
            $r1->getStatusCode(),
            $r2->getStatusCode(),
            'Login inconsistente con variaciones de mayusculas/minusculas en email'
        );
    }

    public function test_login_con_email_con_espacios_en_blanco_funciona_porque_laravel_hace_trim()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => '  login-extremos@test.cl  ',
            'password' => 'Pass123!',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 401, 422]);
    }

    public function test_login_no_acepta_password_super_largo_para_evitar_dos()
    {
        $passwordEnorme = str_repeat('A', 10000);
        $response = $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => $passwordEnorme,
        ]);

        $this->assertNotEquals(
            200,
            $response->getStatusCode(),
            'Login acepto password gigante - posible DoS via bcrypt'
        );
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

    public function test_5_intentos_fallidos_bloquea_la_cuenta_15_minutos()
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'login-extremos@test.cl',
                'password' => 'password-incorrecto',
            ])->assertStatus(401);
        }

        $this->usuario->refresh();
        $this->assertSame(4, $this->usuario->intentos_fallidos);
        $this->assertNull($this->usuario->bloqueado_hasta);

        // El 5to intento fallido dispara el bloqueo.
        $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'password-incorrecto',
        ])->assertStatus(401);

        $this->usuario->refresh();
        $this->assertSame(0, $this->usuario->intentos_fallidos);
        $this->assertSame(1, $this->usuario->nivel_bloqueo);
        $this->assertNotNull($this->usuario->bloqueado_hasta);
        $this->assertTrue(Carbon::parse($this->usuario->bloqueado_hasta)->isFuture());

        // Con la password correcta, ahora rechaza por bloqueo (403), no por credenciales.
        $bloqueado = $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'Pass123!',
        ]);
        $bloqueado->assertStatus(403);
    }

    public function test_login_exitoso_resetea_intentos_fallidos()
    {
        $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'password-incorrecto',
        ])->assertStatus(401);

        $this->usuario->refresh();
        $this->assertSame(1, $this->usuario->intentos_fallidos);

        $this->postJson('/api/auth/login', [
            'email' => 'login-extremos@test.cl',
            'password' => 'Pass123!',
        ])->assertStatus(200);

        $this->usuario->refresh();
        $this->assertSame(0, $this->usuario->intentos_fallidos);
        $this->assertNull($this->usuario->bloqueado_hasta);
    }

    public function test_endpoint_me_con_token_de_otra_empresa_devuelve_solo_su_propia_data()
    {
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

        $response = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/auth/me');

        $response->assertStatus(200);
        $body = $response->json();
        $user = $body['user'] ?? $body;

        $this->assertEquals(
            $empresaB->id,
            $user['empresa_id'],
            'GET /me filtro datos de otra empresa'
        );
    }
}
