<?php

namespace Tests\Feature\Integraciones;

use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura del pipeline de autenticacion API-key ('integracion.api.key' + 'integracion.scope')
 * via el endpoint de ping (Etapa 2). El caso "cross-tenant" es el mas critico del dominio:
 * EmpresaScope se apaga sin auth()->check(), asi que la unica defensa multitenant real aca
 * es que la key resuelta trae SU PROPIO empresa_id, nunca el de otra empresa.
 */
class AutenticarApiKeyTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function habilitarModulo($empresa): void
    {
        $this->crearUsuario($empresa, $this->rolAdministrador, [
            'module_keys' => ['integraciones.api'],
        ]);
    }

    public function test_key_valida_con_scope_correcto_pasa(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping');

        $respuesta->assertOk()->assertJsonPath('data.empresa_id', $empresa->id);
    }

    public function test_sin_token_es_rechazado(): void
    {
        $this->getJson('/api/integraciones/v1/ping')->assertUnauthorized();
    }

    public function test_token_invalido_es_rechazado(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer tnri_noexiste_secreto'])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();
    }

    public function test_key_revocada_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer']);
        $servicio->revocar($emitida['key']);

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();
    }

    public function test_key_expirada_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer'], now()->subMinute());

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();
    }

    public function test_key_sin_scope_requerido_es_rechazada_con_403(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:escribir']);

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertForbidden();
    }

    public function test_empresa_sin_modulo_habilitado_es_rechazada(): void
    {
        $empresa = $this->crearEmpresa();
        // Sin habilitarModulo(): ningun usuario de la empresa tiene 'integraciones.api'.

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer']);

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();
    }

    public function test_key_de_una_empresa_nunca_devuelve_datos_de_otra(): void
    {
        $empresaA = $this->crearEmpresa();
        $empresaB = $this->crearEmpresa();
        $this->habilitarModulo($empresaA);
        $this->habilitarModulo($empresaB);

        $servicio = app(IntegracionApiKeyService::class);
        $emitidaA = $servicio->emitir($empresaA->id, 'Key A', ['inventario:leer']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$emitidaA['token']])
            ->getJson('/api/integraciones/v1/ping');

        $respuesta->assertOk();
        $this->assertSame($empresaA->id, $respuesta->json('data.empresa_id'));
        $this->assertNotSame($empresaB->id, $respuesta->json('data.empresa_id'));
    }

    public function test_rotar_invalida_el_token_anterior(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer']);
        $rotada = $servicio->rotar($emitida['key']);

        $this->withHeaders(['Authorization' => 'Bearer '.$emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertUnauthorized();

        $this->withHeaders(['Authorization' => 'Bearer '.$rotada['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertOk();
    }

    public function test_uso_via_header_x_api_key_tambien_funciona(): void
    {
        $empresa = $this->crearEmpresa();
        $this->habilitarModulo($empresa);

        $servicio = app(IntegracionApiKeyService::class);
        $emitida = $servicio->emitir($empresa->id, 'Test', ['inventario:leer']);

        $this->withHeaders(['X-Api-Key' => $emitida['token']])
            ->getJson('/api/integraciones/v1/ping')
            ->assertOk();
    }
}
