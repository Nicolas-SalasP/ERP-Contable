<?php

namespace Tests\Feature\Sii\Jobs;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Jobs\MonitorearVencimientoCertificadosJob;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Models\SiiCertificadoNotificacion;
use App\Domains\Sii\Notifications\CertificadoVencimientoNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class MonitorearVencimientoCertificadosJobTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearEmpresaConEmail(?string $emailIntercambio = null, ?string $emailGeneral = null): Empresa
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empresa->update([
            'email_intercambio_sii' => $emailIntercambio,
            'email'                 => $emailGeneral,
        ]);

        return $empresa->fresh();
    }

    private function crearCertDeEmpresa(Empresa $empresa, int $diasParaVencer, string $estado = SiiCertificadoEmpresa::ESTADO_ACTIVO): SiiCertificadoEmpresa
    {
        return SiiCertificadoEmpresa::create([
            'empresa_id'        => $empresa->id,
            'pfx_cifrado'       => 'fake_blob',
            'password_cifrada'  => 'fake_pwd',
            'subject_common_name' => 'Empresa Test',
            'subject_rut'       => '11111111-1',
            'issuer_common_name' => 'E-CertChile',
            'valido_desde'      => now()->subYear(),
            'valido_hasta'      => now()->addDays($diasParaVencer),
            'estado'            => $estado,
        ]);
    }

    public function test_envia_notificacion_BAJA_T60_una_vez(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 45); // BAJA_T60

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertSentOnDemandTimes(CertificadoVencimientoNotification::class, 1);
        $this->assertSame(1, SiiCertificadoNotificacion::where('nivel', 'BAJA_T60')->count());
    }

    public function test_no_re_envia_BAJA_T60_en_segunda_ejecucion(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 45);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());
        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        // Sigue siendo 1 envio porque BAJA_T60 es one-shot.
        Notification::assertSentOnDemandTimes(CertificadoVencimientoNotification::class, 1);
        $this->assertSame(1, SiiCertificadoNotificacion::where('nivel', 'BAJA_T60')->count());
    }

    public function test_envia_diariamente_CRITICA_T7_si_pasa_un_dia(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $cert = $this->crearCertDeEmpresa($empresa, 5); // CRITICA_T7

        // Ejecucion HOY
        Carbon::setTestNow(Carbon::parse('2026-05-23 10:00:00'));
        // Necesitamos que el cert siga en CRITICA_T7 con TestNow fijado:
        $cert->update(['valido_hasta' => now()->addDays(5)]);
        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        // Ejecucion MAÑANA (cert ahora a 4 dias, sigue siendo CRITICA_T7)
        Carbon::setTestNow(Carbon::parse('2026-05-24 10:00:00'));
        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertSentOnDemandTimes(CertificadoVencimientoNotification::class, 2);
        $this->assertSame(2, SiiCertificadoNotificacion::where('nivel', 'CRITICA_T7')->count());
    }

    public function test_no_envia_dos_veces_CRITICA_T7_el_mismo_dia(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 5);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());
        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertSentOnDemandTimes(CertificadoVencimientoNotification::class, 1);
    }

    public function test_skip_si_cert_sin_email_destinatario(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail(null, null);
        $this->crearCertDeEmpresa($empresa, 45);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertNothingSent();
        $this->assertSame(0, SiiCertificadoNotificacion::count());
    }

    public function test_skip_si_nivel_SIN_ALERTA(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 180); // SIN_ALERTA

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertNothingSent();
        $this->assertSame(0, SiiCertificadoNotificacion::count());
    }

    public function test_persiste_notificacion_con_estado_enviada(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $cert    = $this->crearCertDeEmpresa($empresa, 10); // ALTA_T15

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        $notif = SiiCertificadoNotificacion::where('certificado_id', $cert->id)->first();
        $this->assertNotNull($notif);
        $this->assertSame('ALTA_T15', $notif->nivel);
        $this->assertSame('alertas@empresa.cl', $notif->enviada_a);
        $this->assertSame('enviada', $notif->estado_envio);
        $this->assertNull($notif->error_mensaje);
    }

    public function test_destinatario_prioriza_email_intercambio_sii_sobre_email_general(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('intercambio@empresa.cl', 'general@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 45);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertSentOnDemand(
            CertificadoVencimientoNotification::class,
            function ($notification, $channels, $notifiable) {
                /** @var AnonymousNotifiable $notifiable */
                return $notifiable->routes['mail'] === 'intercambio@empresa.cl';
            }
        );
    }

    public function test_fallback_a_email_general_si_intercambio_es_nulo(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail(null, 'general@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 45);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertSentOnDemand(
            CertificadoVencimientoNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'general@empresa.cl';
            }
        );
    }

    public function test_aislamiento_multitenant_no_mezcla_certificados_entre_empresas(): void
    {
        Notification::fake();

        $empresaA = $this->crearEmpresaConEmail('a@e.cl');
        $empresaB = $this->crearEmpresaConEmail('b@e.cl');

        $this->crearCertDeEmpresa($empresaA, 45);
        $this->crearCertDeEmpresa($empresaB, 200); // SIN_ALERTA, no debe enviar

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        // Solo se envio a empresa A.
        Notification::assertSentOnDemandTimes(CertificadoVencimientoNotification::class, 1);
        Notification::assertSentOnDemand(
            CertificadoVencimientoNotification::class,
            function ($n, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'a@e.cl';
            }
        );
    }

    public function test_certs_en_cuarentena_no_se_procesan(): void
    {
        Notification::fake();

        $empresa = $this->crearEmpresaConEmail('alertas@empresa.cl');
        $this->crearCertDeEmpresa($empresa, 5, SiiCertificadoEmpresa::ESTADO_CUARENTENA);

        dispatch_sync(new MonitorearVencimientoCertificadosJob());

        Notification::assertNothingSent();
    }
}
