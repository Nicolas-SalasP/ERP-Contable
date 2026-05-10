<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use Laravel\Sanctum\Sanctum;
use App\Domains\Core\Models\User;

class TokensYAutenticacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin([], [
            'email' => 'tokenstest@erp.cl',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_login_correcto_genera_un_token_nuevo()
    {
        $tokensIniciales = $this->usuario->tokens()->count();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $tokensFinales = $this->usuario->fresh()->tokens()->count();
        $this->assertEquals(
            $tokensIniciales + 1,
            $tokensFinales,
            'Login no genero un nuevo token'
        );
    }

    public function test_dos_logins_consecutivos_generan_dos_tokens_distintos()
    {
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $token1 = $r1->json('token');

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $token2 = $r2->json('token');

        $this->assertNotNull($token1);
        $this->assertNotNull($token2);
        $this->assertNotEquals(
            $token1,
            $token2,
            'Dos logins consecutivos generaron el mismo token'
        );
    }

    public function test_logout_invalida_solo_el_token_actual_y_no_otros()
    {
        $r1 = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $tokenPC = $r1->json('token');

        $r2 = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $tokenMobil = $r2->json('token');

        $this->withHeader('Authorization', 'Bearer ' . $tokenPC)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $hashedPC = hash('sha256', explode('|', $tokenPC, 2)[1] ?? $tokenPC);
        $tokenPCEnBD = \DB::table('personal_access_tokens')
            ->where('token', $hashedPC)
            ->exists();
        $this->assertFalse(
            $tokenPCEnBD,
            'Logout no elimino el token de la BD'
        );

        $hashedMobil = hash('sha256', explode('|', $tokenMobil, 2)[1] ?? $tokenMobil);
        $tokenMobilEnBD = \DB::table('personal_access_tokens')
            ->where('token', $hashedMobil)
            ->exists();
        $this->assertTrue(
            $tokenMobilEnBD,
            'Logout elimino el token de OTRA sesion - bug critico'
        );
    }

    public function test_token_alterado_un_caracter_no_funciona()
    {
        $r = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $token = $r->json('token');

        $tokenAlterado = substr($token, 0, -1) . 'X';

        $response = $this->withHeader('Authorization', 'Bearer ' . $tokenAlterado)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_endpoints_protegidos_rechazan_token_de_usuario_eliminado()
    {
        $usuarioAEliminar = $this->crearUsuario($this->empresa, $this->rolUsuarioBasico, [
            'email' => 'eliminar@erp.cl',
            'password' => bcrypt('password123'),
        ]);

        $r = $this->postJson('/api/auth/login', [
            'email' => 'eliminar@erp.cl',
            'password' => 'password123',
        ]);
        $response = $r;
        $token = $response->json('token');

        $this->assertGreaterThan(
            0,
            \DB::table('personal_access_tokens')
                ->where('tokenable_id', $usuarioAEliminar->id)
                ->count()
        );

        Sanctum::actingAs($this->usuario);
        $this->deleteJson("/api/usuarios/{$usuarioAEliminar->id}")
            ->assertStatus(200);

        $existeDespues = \DB::table('personal_access_tokens')
            ->where('tokenable_id', $usuarioAEliminar->id)
            ->exists();

        $this->assertFalse(
            $existeDespues,
            'Tokens del usuario desvinculado no fueron revocados - VULNERABILIDAD: ' .
            'el usuario puede seguir usando el sistema con su token hasta que expire'
        );
    }

    public function test_login_devuelve_estructura_user_sin_password()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $body = $response->json();

        $this->assertArrayHasKey('user', $body);
        $userBody = $body['user'];

        $this->assertArrayNotHasKey(
            'password',
            $userBody,
            'Endpoint login filtra el password hasheado'
        );
        $this->assertArrayNotHasKey('remember_token', $userBody);
    }

    public function test_authorization_header_sin_bearer_es_rechazado()
    {
        $r = $this->postJson('/api/auth/login', [
            'email' => 'tokenstest@erp.cl',
            'password' => 'password123',
        ]);
        $token = $r->json('token');

        $response = $this->withHeader('Authorization', $token)
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }
}
