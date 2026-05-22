<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Config;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin([], [
            'email' => 'expiration-test@test.cl',
            'password' => bcrypt('Pass123!'),
        ]);
    }

    public function test_config_sanctum_tiene_expiration_configurado()
    {
        $expiration = Config::get('sanctum.expiration');

        $this->assertNotNull($expiration,
            'sanctum.expiration NO debe ser null - debe tener minutos configurados');
        $this->assertGreaterThan(0, $expiration,
            'sanctum.expiration debe ser un numero positivo de minutos');
        $this->assertEquals(120, $expiration,
            'Por defecto sanctum.expiration debe ser 120 minutos (2 horas)');
    }

    public function test_token_recien_creado_no_esta_expirado()
    {
        $plainToken = $this->usuario->createToken('test-token')->plainTextToken;
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('email', 'expiration-test@test.cl');
    }

    public function test_token_creado_hace_mas_de_la_expiracion_es_rechazado()
    {
        $plainToken = $this->usuario->createToken('test-token')->plainTextToken;
        $token = PersonalAccessToken::findToken($plainToken);
        $this->assertNotNull($token, 'El token recien creado deberia existir en BD');
        $token->created_at = now()->subHours(3);
        $token->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_token_creado_dentro_del_periodo_de_expiracion_sigue_funcionando()
    {
        $plainToken = $this->usuario->createToken('test-token')->plainTextToken;
        $token = PersonalAccessToken::findToken($plainToken);
        $token->created_at = now()->subHour();
        $token->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200,
            'Un token de 1 hora deberia seguir funcionando con expiration=120');
    }

    public function test_token_expirado_NO_se_puede_refrescar()
    {
        $plainToken = $this->usuario->createToken('test-token')->plainTextToken;
        $token = PersonalAccessToken::findToken($plainToken);
        $token->created_at = now()->subHours(3);
        $token->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainToken,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(401,
            'El refresh con token expirado debe ser rechazado, no permite "resurrected sessions"');
    }

    public function test_token_recien_refrescado_tiene_expiracion_nueva()
    {
        $plainTokenViejo = $this->usuario->createToken('viejo')->plainTextToken;
        $tokenViejo = PersonalAccessToken::findToken($plainTokenViejo);
        $tokenViejo->created_at = now()->subHour();
        $tokenViejo->save();

        $refreshResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTokenViejo,
        ])->postJson('/api/auth/refresh');

        $refreshResponse->assertStatus(200);
        $plainTokenNuevo = $refreshResponse->json('token');

        $tokenNuevo = PersonalAccessToken::findToken($plainTokenNuevo);
        $this->assertNotNull($tokenNuevo, 'El token nuevo debe estar en BD');

        $segundosDesdeCreacion = now()->diffInSeconds($tokenNuevo->created_at);
        $this->assertLessThan(5, $segundosDesdeCreacion,
            'created_at del token nuevo debe ser cercano a now() (no heredar del viejo)');

        $tokenNuevo->created_at = now()->subHour()->subMinutes(30);
        $tokenNuevo->save();

        $checkResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTokenNuevo,
        ])->getJson('/api/auth/me');
        $checkResponse->assertStatus(200);
    }

    public function test_login_crea_token_con_created_at_actual()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'expiration-test@test.cl',
            'password' => 'Pass123!',
        ]);

        $response->assertStatus(200);
        $plainToken = $response->json('token');
        $this->assertNotEmpty($plainToken);

        $token = PersonalAccessToken::findToken($plainToken);
        $this->assertNotNull($token);

        $segundos = now()->diffInSeconds($token->created_at);
        $this->assertLessThan(5, $segundos,
            'Token de login debe tener created_at cercano a now()');
    }
}
