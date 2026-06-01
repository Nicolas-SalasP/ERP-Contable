<?php

namespace Tests\Feature\Sii\Notifications;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Notifications\CertificadoVencimientoNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use ReflectionClass;
use Tests\TestCase;

class CertificadoVencimientoNotificationTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function certDeMuestra(int $diasFuturo): SiiCertificadoEmpresa
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 10:00:00'));

        $cert = new SiiCertificadoEmpresa([
            'subject_common_name' => 'Empresa Test',
            'subject_rut'         => '76086428-5',
            'issuer_common_name'  => 'E-CertChile',
            'valido_desde'        => now()->subYear(),
            'valido_hasta'        => now()->addDays($diasFuturo),
            'estado'              => SiiCertificadoEmpresa::ESTADO_ACTIVO,
        ]);
        // Forzar el cast de fecha sin necesidad de save().
        $cert->valido_hasta = now()->addDays($diasFuturo);

        return $cert;
    }

    private function mensaje(int $dias, string $nivel): MailMessage
    {
        $cert  = $this->certDeMuestra($dias);
        $notif = new CertificadoVencimientoNotification($cert, $nivel);

        return $notif->toMail(new AnonymousNotifiable());
    }

    public function test_subject_VENCIDO_contiene_URGENTE(): void
    {
        $msg = $this->mensaje(-3, SiiCertificadoEmpresa::ALERTA_VENCIDO);
        $this->assertStringContainsString('URGENTE', $msg->subject);
        $this->assertStringContainsString('vencido', strtolower($msg->subject));
    }

    public function test_subject_CRITICA_T1_contiene_MANANA(): void
    {
        $msg = $this->mensaje(1, SiiCertificadoEmpresa::ALERTA_CRITICA_T1);
        $this->assertStringContainsString('CRITICO', $msg->subject);
        $this->assertStringContainsString('MANANA', $msg->subject);
    }

    public function test_subject_otros_niveles_contiene_dias_restantes(): void
    {
        foreach ([
            [10, SiiCertificadoEmpresa::ALERTA_ALTA_T15],
            [25, SiiCertificadoEmpresa::ALERTA_MEDIA_T30],
            [50, SiiCertificadoEmpresa::ALERTA_BAJA_T60],
        ] as [$dias, $nivel]) {
            $msg = $this->mensaje($dias, $nivel);
            $this->assertStringContainsString((string) $dias, $msg->subject, "Subject para nivel {$nivel} no contiene los dias.");
            $this->assertStringContainsString('vence en', strtolower($msg->subject));
        }
    }

    public function test_markdown_view_renderiza_con_datos_del_cert(): void
    {
        $msg = $this->mensaje(45, SiiCertificadoEmpresa::ALERTA_BAJA_T60);

        $rendered = (string) $msg->render();

        $this->assertStringContainsString('Empresa Test', $rendered);
        $this->assertStringContainsString('76086428-5', $rendered);
        $this->assertStringContainsString('E-CertChile', $rendered);
        $this->assertStringContainsString('Renovar Certificado', $rendered);
    }

    public function test_implementa_ShouldQueue(): void
    {
        $r = new ReflectionClass(CertificadoVencimientoNotification::class);
        $this->assertTrue($r->implementsInterface(ShouldQueue::class));
    }
}
