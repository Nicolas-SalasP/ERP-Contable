<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshTokenTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin([], [
            'email' => 'refresh-test@test.cl',
            'password' => bcrypt('Pass123!'),
        ]);
    }

    public function test_refresh_devuelve_nuevo_token_cuando_usuario_esta_autenticado()
    {
        $tokenViejo = $this->usuario->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenViejo,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'token',
            'issued_at',
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEquals($tokenViejo, $response->json('token'));
    }

    public function test_token_viejo_queda_revocado_despues_de_refresh()
    {
        $tokenViejo = $this->usuario->createToken('test-token')->plainTextToken;
        $tokensAntes = PersonalAccessToken::where('tokenable_id', $this->usuario->id)->count();
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenViejo,
        ])->postJson('/api/auth/refresh');
        $response->assertStatus(200);

        $tokensDespues = PersonalAccessToken::where('tokenable_id', $this->usuario->id)->count();

        $this->assertEquals($tokensAntes, $tokensDespues,
            'Debe quedar la misma cantidad de tokens (viejo eliminado, nuevo creado)');

        $patViejo = PersonalAccessToken::findToken($tokenViejo);
        $this->assertNull($patViejo,
            'El token viejo deberia estar eliminado de la BD despues del refresh');
    }

    public function test_token_nuevo_funciona_para_otros_endpoints()
    {
        $tokenViejo = $this->usuario->createToken('test-token')->plainTextToken;

        $refresh = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenViejo,
        ])->postJson('/api/auth/refresh');

        $tokenNuevo = $refresh->json('token');
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenNuevo,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('email', 'refresh-test@test.cl');
    }

    public function test_refresh_sin_token_rechaza_con_401()
    {
        $response = $this->postJson('/api/auth/refresh');
        $response->assertStatus(401);
    }

    public function test_refresh_con_token_invalido_rechaza_con_401()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer token-falso-que-no-existe',
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(401);
    }

    public function test_refresh_incluye_timestamp_iso_para_que_frontend_calcule_proximidad()
    {
        $tokenViejo = $this->usuario->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $tokenViejo,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200);
        $issuedAt = $response->json('issued_at');
        $this->assertNotEmpty($issuedAt);
        $timestamp = strtotime($issuedAt);
        $this->assertGreaterThan(0, $timestamp,
            'issued_at debe ser ISO 8601 valido para que el FE pueda parsearlo');

        $this->assertLessThan(5, time() - $timestamp,
            'issued_at debe estar cerca de now()');
    }

    public function test_se_pueden_hacer_multiples_refresh_consecutivos()
    {
        $token = $this->usuario->createToken('test-token')->plainTextToken;

        for ($i = 1; $i <= 3; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/auth/refresh');

            $response->assertStatus(200, "Refresh #{$i} fallo");
            $tokenNuevo = $response->json('token');
            $this->assertNotEmpty($tokenNuevo, "Refresh #{$i} no devolvio token");
            $this->assertNotEquals($token, $tokenNuevo, "Refresh #{$i} no roto el token");

            $token = $tokenNuevo;
        }

        $final = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');
        $final->assertStatus(200);
    }
}
