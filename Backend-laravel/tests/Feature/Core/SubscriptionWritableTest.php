<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * H9: el middleware subscription.writable bloquea escrituras de suscripciones
 * read_only/expired, pero deja pasar lecturas. La firma propia del guard es el
 * code 'SUBSCRIPTION_READ_ONLY', por eso se asierta sobre él (independiente de
 * la lógica del controller que haya detrás).
 *
 * Los usuarios se crean sin tenri_user_id para que check.subscription pase
 * fail-open y el único guard en juego sea subscription.writable.
 */
class SubscriptionWritableTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_read_only_bloquea_escritura_con_403(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin([], ['subscription_status' => 'read_only']);
        Sanctum::actingAs($usuario);

        $this->postJson('/api/empresas/bancos', [])
            ->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_READ_ONLY']);
    }

    public function test_expired_bloquea_escritura_con_403(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin([], ['subscription_status' => 'expired']);
        Sanctum::actingAs($usuario);

        $this->putJson('/api/empresas/perfil', [])
            ->assertStatus(403)
            ->assertJson(['code' => 'SUBSCRIPTION_READ_ONLY']);
    }

    public function test_read_only_permite_lectura(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin([], ['subscription_status' => 'read_only']);
        Sanctum::actingAs($usuario);

        // GET pasa el guard: nunca lleva el code de bloqueo.
        $this->getJson('/api/empresas/perfil')
            ->assertJsonMissing(['code' => 'SUBSCRIPTION_READ_ONLY']);
    }

    public function test_active_permite_escritura(): void
    {
        [, $usuario] = $this->crearEmpresaConAdmin([], ['subscription_status' => 'active']);
        Sanctum::actingAs($usuario);

        // El guard deja pasar; lo que responda el controller (422 por payload vacío,
        // etc.) no es asunto del middleware, pero nunca debe ser el bloqueo de suscripción.
        $this->postJson('/api/empresas/bancos', [])
            ->assertJsonMissing(['code' => 'SUBSCRIPTION_READ_ONLY']);
    }
}
