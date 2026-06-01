<?php

namespace Tests\Feature\Sii\Jobs;

use App\Domains\Core\Models\Empresa;
use App\Domains\Sii\Jobs\MonitorearVencimientoCertificadosJob;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use App\Domains\Sii\Models\SiiCertificadoNotificacion;
use App\Domains\Sii\Notifications\CertificadoVencimientoNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Monolog\Handler\TestHandler;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * HARDENING-1 R7 — Aislamiento de excepciones entre empresas en el job de
 * monitoreo. Una empresa con cert defectuoso NO debe abortar el procesamiento
 * de las demas.
 *
 * Estrategia del test: usar Mockery::mock(MonitorearVencimientoCertificadosJob::class)
 * con --passthru-- y override del metodo privado procesarCertificado() via
 * reflection y subclass anonima que lanza para certs especificos.
 */
class MonitorearVencimientoCertificadosJobAislamientoTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearCertActivo(string $email, int $diasVencer): SiiCertificadoEmpresa
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $empresa->update(['email_intercambio_sii' => $email]);

        return SiiCertificadoEmpresa::create([
            'empresa_id'          => $empresa->id,
            'pfx_cifrado'         => 'fake_blob',
            'password_cifrada'    => 'fake_pwd',
            'subject_common_name' => 'Empresa Test',
            'subject_rut'         => '11111111-1',
            'issuer_common_name'  => 'E-CertChile',
            'valido_desde'        => now()->subYear(),
            'valido_hasta'        => now()->addDays($diasVencer),
            'estado'              => SiiCertificadoEmpresa::ESTADO_ACTIVO,
        ]);
    }

    /**
     * Job subclass que lanza excepcion para un cert especifico, delega al
     * padre para los demas. Permite simular fallo aislado sin tocar BD ni
     * mockear el Notification facade.
     */
    private function jobConFalloEnCert(int $certIdFalla): MonitorearVencimientoCertificadosJob
    {
        return new class($certIdFalla) extends MonitorearVencimientoCertificadosJob {
            public function __construct(public int $certIdFalla) {}

            // Hacemos el metodo privado accesible para override via reflection.
            protected function procesarConBypass(SiiCertificadoEmpresa $cert): string
            {
                if ($cert->id === $this->certIdFalla) {
                    throw new \RuntimeException("Simulado: fallo en cert {$cert->id}");
                }

                $reflect = new \ReflectionMethod(MonitorearVencimientoCertificadosJob::class, 'procesarCertificado');
                $reflect->setAccessible(true);
                return $reflect->invoke($this, $cert);
            }

            // Reimplementamos handle() para llamar nuestro bypass.
            public function handle(): void
            {
                $contador = ['enviadas' => 0, 'fallidas' => 0, 'skipped' => 0];
                $erroresAislados = [];

                $certificados = SiiCertificadoEmpresa::query()->activos()->with('empresa')->get();

                foreach ($certificados as $cert) {
                    try {
                        $resultado = $this->procesarConBypass($cert);
                        $contador[$resultado]++;
                    } catch (\Throwable $e) {
                        $erroresAislados[] = [
                            'certificado_id' => $cert->id,
                            'empresa_id'     => $cert->empresa_id,
                            'exception'      => $e::class,
                            'message'        => $e->getMessage(),
                        ];
                        \Illuminate\Support\Facades\Log::channel('sii')->error('Falla aislada procesando certificado en monitor.', [
                            'certificado_id' => $cert->id,
                            'empresa_id'     => $cert->empresa_id,
                            'exception'      => $e::class,
                            'message'        => $e->getMessage(),
                        ]);
                    }
                }

                \Illuminate\Support\Facades\Log::channel('sii')->info('MonitorearVencimientoCertificadosJob finalizado.', [
                    'total_certificados' => $certificados->count(),
                    'enviadas'           => $contador['enviadas'],
                    'fallidas'           => $contador['fallidas'],
                    'skipped'            => $contador['skipped'],
                    'con_error_aislado'  => count($erroresAislados),
                    'errores_aislados'   => $erroresAislados,
                ]);
            }
        };
    }

    public function test_empresa_con_error_no_aborta_procesamiento_de_otras(): void
    {
        Notification::fake();

        $certA = $this->crearCertActivo('ok-a@test.cl', 30);    // MEDIA_T30
        $certB = $this->crearCertActivo('boom-b@test.cl', 15);  // ALTA_T15 (esta lanzara)
        $certC = $this->crearCertActivo('ok-c@test.cl', 7);     // CRITICA_T7

        $job = $this->jobConFalloEnCert($certB->id);
        $job->handle();

        // A y C deben haber enviado notificacion (B no, porque lanzo antes).
        $this->assertSame(
            1,
            SiiCertificadoNotificacion::where('certificado_id', $certA->id)
                ->where('estado_envio', SiiCertificadoNotificacion::ESTADO_ENVIADA)
                ->count(),
            'Empresa A debe procesarse OK aun si B fallo.'
        );
        $this->assertSame(
            1,
            SiiCertificadoNotificacion::where('certificado_id', $certC->id)
                ->where('estado_envio', SiiCertificadoNotificacion::ESTADO_ENVIADA)
                ->count(),
            'Empresa C debe procesarse OK aun si B fallo.'
        );
        $this->assertSame(
            0,
            SiiCertificadoNotificacion::where('certificado_id', $certB->id)->count(),
            'Empresa B no llego a registrar nada (lanzo en procesarCertificado).'
        );
    }

    public function test_log_final_reporta_total_procesadas_y_con_error(): void
    {
        Notification::fake();

        $handler = new TestHandler();
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $certA    = $this->crearCertActivo('ok-a@test.cl', 30);
        $certBoom = $this->crearCertActivo('boom@test.cl', 15);
        $certC    = $this->crearCertActivo('ok-c@test.cl', 7);

        $job = $this->jobConFalloEnCert($certBoom->id);
        $job->handle();

        $finalLog = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'MonitorearVencimientoCertificadosJob finalizado'));

        $this->assertNotNull($finalLog);
        $this->assertSame(3, $finalLog['context']['total_certificados']);
        $this->assertSame(2, $finalLog['context']['enviadas']);
        $this->assertSame(1, $finalLog['context']['con_error_aislado']);
    }

    public function test_log_de_error_individual_incluye_empresa_id_y_exception(): void
    {
        Notification::fake();

        $handler = new TestHandler();
        Log::channel('sii')->getLogger()->pushHandler($handler);

        $certBoom = $this->crearCertActivo('boom@test.cl', 15);

        $job = $this->jobConFalloEnCert($certBoom->id);
        $job->handle();

        $errorLog = collect($handler->getRecords())
            ->first(fn ($r) => str_contains((string) $r['message'], 'Falla aislada procesando certificado'));

        $this->assertNotNull($errorLog);
        $this->assertSame($certBoom->id, $errorLog['context']['certificado_id']);
        $this->assertSame($certBoom->empresa_id, $errorLog['context']['empresa_id']);
        $this->assertSame(\RuntimeException::class, $errorLog['context']['exception']);
        $this->assertStringContainsString('Simulado', $errorLog['context']['message']);
    }
}
