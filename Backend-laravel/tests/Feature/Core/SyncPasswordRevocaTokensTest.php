<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SyncPasswordRevocaTokensTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        config(['services.tenri_web.web_integration_key' => 'test-web-key']);
    }

    public function test_sync_password_revoca_los_tokens_del_usuario(): void
    {
        Route::middleware('auth:sanctum')->get('/__fase23/token-check', function (Request $request) {
            return response()->json([
                'success' => true,
                'user_id' => $request->user()->id,
            ]);
        });

        $empresa = Empresa::create([
            'rut' => '83.000.000-3',
            'razon_social' => 'Pass SpA',
            'regimen_tributario' => '14_D3',
        ]);

        $user = User::create([
            'nombre' => 'Usuario Password',
            'email' => 'u@pass.cl',
            'password' => bcrypt('vieja'),
            'empresa_id' => $empresa->id,
            'rol_id' => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
            'tenri_user_id' => 909,
        ]);

        $plainToken = $user->createToken('erp-token')->plainTextToken;

        $this->assertSame(1, $user->tokens()->count());
        $this->assertNotNull(PersonalAccessToken::findToken($plainToken));

        $this->postJson('/api/internal/web/sync-password', [
            'tenri_user_id' => 909,
            'password_hash' => bcrypt('nueva'),
        ], ['X-WEB-API-KEY' => 'test-web-key'])->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
        $this->assertNull(PersonalAccessToken::findToken($plainToken));

        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/__fase23/token-check')
            ->assertUnauthorized();
    }
}
