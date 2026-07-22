<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Jobs\EnviarCotizacionCorreoJob;
use App\Domains\Comercial\Jobs\EnviarFacturaCorreoJob;
use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Notifications\CotizacionEnviadaNotification;
use App\Domains\Comercial\Notifications\FacturaEmitidaNotification;
use App\Domains\Comercial\Services\CotizacionService;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class NotificacionesCotizacionFacturaTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private Empresa $empresa;

    private User $usuario;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        $this->empresa = Empresa::create([
            'rut' => '11.111.111-1',
            'razon_social' => 'Empresa Notificaciones SpA',
            'email' => 'contacto@empresademo.cl',
        ]);
        $this->usuario = User::create([
            'nombre' => 'Vendedor Uno',
            'email' => 'vendedor@empresademo.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $this->cliente = Cliente::create([
            'empresa_id' => $this->empresa->id,
            'rut' => '22.222.222-2',
            'razon_social' => 'Cliente Demo',
            'contacto_email' => 'cliente@externo.cl',
            'estado' => 'ACTIVO',
        ]);

        foreach ([
            ['152005', 'Clientes CxC', 'ACTIVO'],
            ['501105', 'Ventas', 'INGRESO'],
            ['353360', 'IVA Débito Fiscal', 'PASIVO'],
        ] as [$cod, $nom, $tipo]) {
            PlanCuenta::create([
                'empresa_id' => $this->empresa->id,
                'codigo' => $cod,
                'nombre' => $nom,
                'tipo' => $tipo,
                'imputable' => true,
                'activo' => true,
            ]);
        }
    }

    private function crearCotizacion(): Cotizacion
    {
        $estado = EstadoCotizacion::firstOrCreate(['nombre' => 'Aceptada']);

        return Cotizacion::create([
            'empresa_id' => $this->empresa->id,
            'cliente_id' => $this->cliente->id,
            'nombre_cliente' => $this->cliente->razon_social,
            'numero_cotizacion' => 'COT-TEST-'.uniqid(),
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 100000,
            'monto_neto' => 100000,
            'monto_iva' => 19000,
            'monto_total' => 119000,
            'estado_id' => $estado->id,
        ]);
    }

    // -------------------------------------------------------------------
    // Cotización: acción explícita "Enviar"
    // -------------------------------------------------------------------

    public function test_endpoint_enviar_cotizacion_requiere_permiso(): void
    {
        $cotizacion = $this->crearCotizacion();

        $response = $this->postJson("/api/cotizaciones/{$cotizacion->id}/enviar");

        $response->assertStatus(401); // sin auth
    }

    public function test_enviar_cotizacion_encola_el_job_y_no_pasa_sola(): void
    {
        Queue::fake();

        $cotizacion = $this->crearCotizacion();

        $this->actingAs($this->usuario)
            ->postJson("/api/cotizaciones/{$cotizacion->id}/enviar")
            ->assertOk();

        Queue::assertPushed(EnviarCotizacionCorreoJob::class);

        // Crear la cotizacion NO debe haber encolado nada por si sola.
        Queue::assertPushed(EnviarCotizacionCorreoJob::class, 1);
    }

    public function test_job_envia_solo_al_email_interno_de_la_empresa_no_al_cliente(): void
    {
        Notification::fake();
        config(['notificaciones.cliente_habilitado' => false]);

        $cotizacion = $this->crearCotizacion();

        (new EnviarCotizacionCorreoJob($this->empresa->id, $cotizacion->id, $this->usuario->id))->handle();

        Notification::assertSentOnDemand(
            CotizacionEnviadaNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'contacto@empresademo.cl';
            }
        );

        // Nunca debe intentar mandarle nada al cliente mientras el flag este apagado.
        Notification::assertNotSentTo(
            new AnonymousNotifiable,
            CotizacionEnviadaNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'cliente@externo.cl';
            }
        );

        $cotizacion->refresh();
        $this->assertNotNull($cotizacion->enviada_at);
        $this->assertEquals($this->usuario->id, $cotizacion->usuario_envio_id);
    }

    public function test_job_envia_tambien_al_cliente_cuando_el_flag_esta_activo(): void
    {
        Notification::fake();
        config(['notificaciones.cliente_habilitado' => true]);

        $cotizacion = $this->crearCotizacion();

        (new EnviarCotizacionCorreoJob($this->empresa->id, $cotizacion->id, $this->usuario->id))->handle();

        Notification::assertSentOnDemandTimes(CotizacionEnviadaNotification::class, 2);
    }

    public function test_job_no_falla_si_empresa_no_tiene_email_configurado(): void
    {
        Notification::fake();

        $empresaSinEmail = Empresa::create(['rut' => '33.333.333-3', 'razon_social' => 'Sin Email SpA']);
        $cliente = Cliente::create([
            'empresa_id' => $empresaSinEmail->id,
            'rut' => '44.444.444-4',
            'razon_social' => 'Cliente X',
            'estado' => 'ACTIVO',
        ]);
        $estado = EstadoCotizacion::firstOrCreate(['nombre' => 'Aceptada']);
        $cotizacion = Cotizacion::create([
            'empresa_id' => $empresaSinEmail->id,
            'cliente_id' => $cliente->id,
            'nombre_cliente' => $cliente->razon_social,
            'numero_cotizacion' => 'COT-SIN-EMAIL',
            'fecha_emision' => now()->format('Y-m-d'),
            'fecha_validez' => now()->addDays(30)->format('Y-m-d'),
            'validez' => 30,
            'subtotal' => 1000,
            'monto_neto' => 1000,
            'monto_iva' => 190,
            'monto_total' => 1190,
            'estado_id' => $estado->id,
        ]);

        (new EnviarCotizacionCorreoJob($empresaSinEmail->id, $cotizacion->id, null))->handle();

        Notification::assertNothingSent();
        $this->assertNull($cotizacion->fresh()->enviada_at);
    }

    // -------------------------------------------------------------------
    // Factura: automático al facturar
    // -------------------------------------------------------------------

    public function test_facturar_dispara_notificacion_automaticamente(): void
    {
        Notification::fake();

        $cotizacion = $this->crearCotizacion();

        $factura = app(CotizacionService::class)->convertirEnFactura(
            $this->empresa->id,
            $cotizacion->id,
            null,
            $this->usuario->id
        );

        Notification::assertSentOnDemand(
            FacturaEmitidaNotification::class,
            function ($notification, $channels, $notifiable) use ($factura) {
                return $notifiable->routes['mail'] === 'contacto@empresademo.cl'
                    && $notification->factura->id === $factura->id;
            }
        );

        $factura->refresh();
        $this->assertNotNull($factura->notificada_at);
        $this->assertEquals($this->usuario->id, $factura->usuario_notificacion_id);
    }

    public function test_job_de_factura_aislado_por_empresa_no_encuentra_factura_de_otra(): void
    {
        Notification::fake();

        $cotizacion = $this->crearCotizacion();
        $factura = app(CotizacionService::class)->convertirEnFactura($this->empresa->id, $cotizacion->id);

        $otraEmpresa = Empresa::create(['rut' => '55.555.555-5', 'razon_social' => 'Otra SpA', 'email' => 'otra@empresa.cl']);

        Notification::fake();
        (new EnviarFacturaCorreoJob($otraEmpresa->id, $factura->id, null))->handle();

        // La factura pertenece a $this->empresa, no a $otraEmpresa -- el job no debe encontrarla ni notificar nada.
        Notification::assertNothingSent();
    }
}
