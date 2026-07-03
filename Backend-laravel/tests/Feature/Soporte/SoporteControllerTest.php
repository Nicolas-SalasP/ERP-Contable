<?php

namespace Tests\Feature\Soporte;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cobertura de SoporteController / SoporteClienteService: proxy HMAC hacia
 * api.tenri.cl para tickets de soporte. Sin BD propia mas alla de empresa_id
 * (todo el estado del ticket vive en tenri.cl); por eso las pruebas se apoyan
 * en Http::fake() para simular el servicio externo.
 */
class SoporteControllerTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Empresa $empresa;
    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, ] = $this->crearEmpresaConAdmin();
        // Super Admin (jerarquia 100) recibe todos los permisos, incluidos
        // soporte.ver/soporte.crear (no forman parte del set de Administrador).
        $this->usuario = $this->crearUsuario($this->empresa, $this->rolSuperAdmin);

        config([
            'services.tenri_web.base_url' => 'https://web.test',
            'services.tenri_web.api_key'  => 'clave-soporte-test',
        ]);
    }

    public function test_crea_ticket_con_datos_validos(): void
    {
        Http::fake([
            'https://web.test/api/internal/erp/tickets' => Http::response([
                'id' => 501,
                'subject' => 'No puedo emitir DTE',
                'status' => 'abierto',
            ], 201),
        ]);

        Sanctum::actingAs($this->usuario);

        $response = $this->postJson('/api/soporte/tickets', [
            'subject'  => 'No puedo emitir DTE',
            'category' => 'ERP',
            'priority' => 'alta',
            'message'  => 'El boton de emitir factura no responde.',
        ]);

        $response->assertStatus(201)->assertJson(['id' => 501]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://web.test/api/internal/erp/tickets'
                && $request['subject'] === 'No puedo emitir DTE'
                && $request['empresa_id'] === $this->empresa->id
                && $request->hasHeader('X-Signature');
        });
    }

    public function test_store_rechaza_datos_incompletos(): void
    {
        Http::fake();

        Sanctum::actingAs($this->usuario);

        // Falta 'subject' y 'message', ambos requeridos.
        $response = $this->postJson('/api/soporte/tickets', [
            'category' => 'ERP',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'message']);

        Http::assertNothingSent();
    }

    public function test_store_categoria_invalida_es_rechazada(): void
    {
        Http::fake();

        Sanctum::actingAs($this->usuario);

        $response = $this->postJson('/api/soporte/tickets', [
            'subject' => 'Consulta',
            'category' => 'CategoriaInexistente',
            'message' => 'Detalle de la consulta.',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['category']);
        Http::assertNothingSent();
    }

    public function test_maneja_fallo_del_servicio_externo(): void
    {
        // El servicio externo responde con error de servidor: SoporteClienteService
        // debe capturarlo (no propagar excepcion) y el controller debe informar 503.
        Http::fake([
            'https://web.test/api/internal/erp/tickets' => Http::response(['message' => 'Error interno'], 500),
        ]);

        Sanctum::actingAs($this->usuario);

        $response = $this->postJson('/api/soporte/tickets', [
            'subject' => 'Ticket que fallará',
            'message' => 'Este ticket no debería crearse.',
        ]);

        $response->assertStatus(503);
    }

    public function test_maneja_timeout_o_error_de_red_del_servicio_externo(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Timeout simulado');
        });

        Sanctum::actingAs($this->usuario);

        $response = $this->postJson('/api/soporte/tickets', [
            'subject' => 'Ticket con timeout',
            'message' => 'Simula caida de red hacia tenri.cl.',
        ]);

        $response->assertStatus(503);
    }

    public function test_listado_pasa_empresa_id_del_usuario_autenticado(): void
    {
        Http::fake([
            'https://web.test/api/internal/erp/tickets*' => Http::response([
                'data' => [],
                'total' => 0,
            ], 200),
        ]);

        Sanctum::actingAs($this->usuario);

        $response = $this->getJson('/api/soporte/tickets');

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/internal/erp/tickets')
                && (int) ($request->data()['empresa_id'] ?? null) === $this->empresa->id;
        });
    }

    public function test_aislamiento_multitenant_envia_la_empresa_correcta_no_la_de_otro_tenant(): void
    {
        $otraEmpresa = $this->crearEmpresa();
        $usuarioOtraEmpresa = $this->crearUsuario($otraEmpresa, $this->rolSuperAdmin);

        Http::fake([
            'https://web.test/api/internal/erp/tickets' => Http::response(['id' => 900], 201),
        ]);

        Sanctum::actingAs($usuarioOtraEmpresa);

        $this->postJson('/api/soporte/tickets', [
            'subject' => 'Ticket de otra empresa',
            'message' => 'Debe viajar con el empresa_id de esta empresa, no de otra.',
        ])->assertStatus(201);

        Http::assertSent(function ($request) use ($otraEmpresa) {
            return $request['empresa_id'] === $otraEmpresa->id
                && $request['empresa_id'] !== $this->empresa->id;
        });
    }

    public function test_endpoint_requiere_autenticacion(): void
    {
        $this->postJson('/api/soporte/tickets', [
            'subject' => 'Sin sesion',
            'message' => 'No deberia poder crear tickets.',
        ])->assertStatus(401);
    }
}
