<?php

namespace Tests\Feature\Sii;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class PingTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    public function test_ping_responde_estado_operativo_para_usuario_autenticado(): void
    {
        $this->prepararEntornoBase();
        [, $usuario] = $this->crearEmpresaConAdmin();

        Sanctum::actingAs($usuario);

        $response = $this->getJson('/api/sii/ping');

        $response->assertStatus(200)
            ->assertJson([
                'modulo' => 'sii',
                'estado' => 'operativo',
            ])
            ->assertJsonStructure([
                'modulo',
                'estado',
                'ambiente',
                'version',
                'timestamp',
            ]);
    }

    public function test_ping_rechaza_solicitud_no_autenticada(): void
    {
        $response = $this->getJson('/api/sii/ping');

        $response->assertStatus(401);
    }
}
