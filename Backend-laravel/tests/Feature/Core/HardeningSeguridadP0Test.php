<?php

namespace Tests\Feature\Core;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cubre los endurecimientos de la auditoria de seguridad (P0):
 *  - A-1: rate-limiting en /auth/login (anti fuerza bruta).
 *  - M-3: EmpresaScope fail-safe (usuario autenticado sin empresa NO ve datos).
 */
class HardeningSeguridadP0Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_login_se_bloquea_tras_demasiados_intentos(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin([], [
            'email' => 'brute@test.cl',
            'password' => bcrypt('password123'),
        ]);

        // El limite es 6 por minuto: los primeros 6 intentos (credenciales malas)
        // NO deben devolver 429.
        for ($i = 0; $i < 6; $i++) {
            $resp = $this->postJson('/api/auth/login', [
                'email' => 'brute@test.cl',
                'password' => 'incorrecta',
            ]);
            $this->assertNotSame(
                429,
                $resp->getStatusCode(),
                "El intento #{$i} no deberia estar throttled todavia"
            );
        }

        // El 7mo intento debe ser bloqueado por el rate limiter.
        $this->postJson('/api/auth/login', [
            'email' => 'brute@test.cl',
            'password' => 'incorrecta',
        ])->assertStatus(429);
    }

    public function test_usuario_sin_empresa_no_ve_datos_de_ninguna_empresa(): void
    {
        // Empresa con un cliente real.
        $empresa = $this->crearEmpresa();
        Cliente::create([
            'rut' => '11.111.111-1',
            'razon_social' => 'Cliente de Empresa A',
            'estado' => 'ACTIVO',
            'empresa_id' => $empresa->id,
        ]);

        // Usuario autenticado pero SIN empresa asignada (caso onboarding).
        $usuarioSinEmpresa = User::create([
            'nombre' => 'Recien Provisionado',
            'email' => 'sinempresa@test.cl',
            'password' => bcrypt('password123'),
            'empresa_id' => null,
            'rol_id' => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);

        Sanctum::actingAs($usuarioSinEmpresa);

        // Fail-safe: no debe ver NINGUN cliente (antes el scope se desactivaba
        // y exponia datos de todas las empresas).
        $this->assertSame(0, Cliente::query()->count());
    }

    public function test_usuario_con_empresa_solo_ve_su_propia_empresa(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();

        Cliente::create([
            'rut' => '22.222.222-2',
            'razon_social' => 'Cliente A',
            'estado' => 'ACTIVO',
            'empresa_id' => $empresaA->id,
        ]);
        Cliente::create([
            'rut' => '33.333.333-3',
            'razon_social' => 'Cliente B',
            'estado' => 'ACTIVO',
            'empresa_id' => $empresaB->id,
        ]);

        $usuarioA = $this->crearUsuario($empresaA);
        Sanctum::actingAs($usuarioA);

        $clientes = Cliente::query()->get();
        $this->assertCount(1, $clientes);
        $this->assertSame($empresaA->id, $clientes->first()->empresa_id);
    }
}
