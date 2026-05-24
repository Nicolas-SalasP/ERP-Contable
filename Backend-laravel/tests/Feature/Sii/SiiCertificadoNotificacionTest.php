<?php

namespace Tests\Feature\Sii;

use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Models\SiiCertificadoNotificacion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class SiiCertificadoNotificacionTest extends TestCase
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

    private function crearCertActivo(): SiiCertificadoEmpresa
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        return SiiCertificadoEmpresa::create([
            'empresa_id'        => $empresa->id,
            'pfx_cifrado'       => 'blob',
            'password_cifrada'  => 'pwd',
            'valido_desde'      => now()->subYear(),
            'valido_hasta'      => now()->addDays(30),
            'estado'            => SiiCertificadoEmpresa::ESTADO_ACTIVO,
        ]);
    }

    public function test_crear_notificacion_basica_persiste(): void
    {
        $cert  = $this->crearCertActivo();
        $notif = SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'MEDIA_T30',
            'enviada_a'        => 'destinatario@empresa.cl',
            'dias_para_vencer' => 30,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now(),
        ]);

        $this->assertNotNull($notif->id);
        $this->assertSame('MEDIA_T30', $notif->nivel);
        $this->assertSame(30, $notif->dias_para_vencer);
        $this->assertInstanceOf(Carbon::class, $notif->enviada_at);
    }

    public function test_cascade_on_delete_desde_certificado(): void
    {
        $cert  = $this->crearCertActivo();
        $notif = SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'BAJA_T60',
            'enviada_a'        => 'd@e.cl',
            'dias_para_vencer' => 45,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now(),
        ]);

        $this->assertSame(1, SiiCertificadoNotificacion::count());

        $cert->delete();

        $this->assertSame(0, SiiCertificadoNotificacion::count());
    }

    public function test_scope_enviadas_filtra_correctamente(): void
    {
        $cert = $this->crearCertActivo();
        SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'BAJA_T60',
            'enviada_a'        => 'a@e.cl',
            'dias_para_vencer' => 45,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now(),
        ]);
        SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'CRITICA_T7',
            'enviada_a'        => 'a@e.cl',
            'dias_para_vencer' => 5,
            'estado_envio'     => 'fallida',
            'enviada_at'       => now(),
        ]);

        $this->assertSame(1, SiiCertificadoNotificacion::enviadas()->count());
        $this->assertSame(1, SiiCertificadoNotificacion::fallidas()->count());
    }

    public function test_scope_hoy_filtra_por_fecha_actual(): void
    {
        $cert = $this->crearCertActivo();

        // Ayer
        SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'CRITICA_T7',
            'enviada_a'        => 'a@e.cl',
            'dias_para_vencer' => 5,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now()->subDay(),
        ]);
        // Hoy
        SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'CRITICA_T7',
            'enviada_a'        => 'a@e.cl',
            'dias_para_vencer' => 4,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now(),
        ]);

        $this->assertSame(2, SiiCertificadoNotificacion::count());
        $this->assertSame(1, SiiCertificadoNotificacion::hoy()->count());
    }

    public function test_relacion_belongs_to_certificado_funciona(): void
    {
        $cert  = $this->crearCertActivo();
        $notif = SiiCertificadoNotificacion::create([
            'certificado_id'   => $cert->id,
            'nivel'            => 'ALTA_T15',
            'enviada_a'        => 'a@e.cl',
            'dias_para_vencer' => 10,
            'estado_envio'     => 'enviada',
            'enviada_at'       => now(),
        ]);

        $this->assertInstanceOf(SiiCertificadoEmpresa::class, $notif->certificado);
        $this->assertSame($cert->id, $notif->certificado->id);
    }
}
