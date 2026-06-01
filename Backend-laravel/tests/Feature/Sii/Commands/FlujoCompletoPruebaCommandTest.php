<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Core\Models\Empresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\OrquestaFlujoCompletoEnTests;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class FlujoCompletoPruebaCommandTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;
    use OrquestaFlujoCompletoEnTests;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        if ($this->localizarOpensslCnf() === null) {
            $this->markTestSkipped('openssl.cnf no encontrado.');
        }

        config([
            'sii.upload.timeout_seconds' => 5,
            'sii.upload.retries'         => 2,
            'sii.upload.retry_delay_ms'  => 1,
        ]);
        Storage::fake(config('sii.storage.disk', 'local'));
    }

    /**
     * Crea la empresa + cert + caf necesarios para que el comando funcione,
     * pero NO crea el DTE (el comando se encarga de crearlo fixture).
     */
    private function empresaListaParaComando(): Empresa
    {
        $ctx = $this->setupEmpresaConFlujoCompleto(['rut' => '76555444-3']);
        // El setup creo un DTE de prueba que NO usaremos (el comando crea su propio fixture).
        $ctx['dte']->delete();
        return $ctx['empresa'];
    }

    public function test_comando_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:flujo-completo-prueba', array_keys(Artisan::all()));
    }

    public function test_escenario_aceptado_imprime_estado_terminal_ACEPTADO(): void
    {
        $empresa = $this->empresaListaParaComando();

        $exit = Artisan::call('sii:flujo-completo-prueba', [
            'empresa_id'   => $empresa->id,
            '--escenario' => 'aceptado',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('=== Flujo completo de emision SII ===', $output);
        $this->assertStringContainsString('Escenario : aceptado', $output);
        $this->assertStringContainsString('HTTP fake : SI', $output);
        $this->assertStringContainsString('--- Paso 5: Polling de estado (F5.3) ---', $output);
        $this->assertStringContainsString('Codigo SII      : EOK', $output);
        $this->assertStringContainsString('Estado final DTE       : ACEPTADO', $output);
        $this->assertStringContainsString('Eventos del DTE        : 3', $output);
    }

    public function test_escenario_rechazado_imprime_estado_terminal_RECHAZADO_con_glosa(): void
    {
        $empresa = $this->empresaListaParaComando();

        Artisan::call('sii:flujo-completo-prueba', [
            'empresa_id'   => $empresa->id,
            '--escenario' => 'rechazado',
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('Codigo SII      : RCH', $output);
        $this->assertStringContainsString('Estado final DTE       : RECHAZADO', $output);
        $this->assertStringContainsString('Envio Rechazado', $output);
    }

    public function test_escenario_procesando_imprime_ENVIADO_SII_y_no_terminal(): void
    {
        $empresa = $this->empresaListaParaComando();

        Artisan::call('sii:flujo-completo-prueba', [
            'empresa_id'   => $empresa->id,
            '--escenario' => 'procesando',
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('Codigo SII      : EPR', $output);
        // EPR no es terminal: DTE sigue en ENVIADO_SII, envio en ENVIADO.
        $this->assertStringContainsString('Estado final DTE       : ENVIADO_SII', $output);
        $this->assertStringContainsString('Estado final envio     : ENVIADO', $output);
        // Solo 2 eventos del DTE (firma + envio), sin transicion terminal.
        $this->assertStringContainsString('Eventos del DTE        : 2', $output);
    }

    public function test_falla_si_empresa_no_existe_con_exit_code_1(): void
    {
        $exit = Artisan::call('sii:flujo-completo-prueba', ['empresa_id' => 999999]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('no encontrada', $output);
    }
}
