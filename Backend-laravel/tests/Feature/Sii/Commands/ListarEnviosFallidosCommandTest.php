<?php

namespace Tests\Feature\Sii\Commands;

use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Models\SiiEnvioDte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ListarEnviosFallidosCommandTest extends TestCase
{
    use RefreshDatabase;
    use PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function envioConEstado(string $estado, array $overrides = []): SiiEnvioDte
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $dte = SiiDteEmitido::factory()->create(['empresa_id' => $empresa->id]);

        return SiiEnvioDte::create(array_merge([
            'empresa_id'     => $empresa->id,
            'dte_emitido_id' => $dte->id,
            'ambiente_sii'   => 'certificacion',
            'estado_envio'   => $estado,
            'track_id'       => 'TRK',
            'intentos_envio' => 1,
            'fecha_envio'    => now(),
        ], $overrides));
    }

    public function test_comando_aparece_en_artisan_list(): void
    {
        $this->assertContains('sii:listar-envios-fallidos', array_keys(Artisan::all()));
    }

    public function test_lista_envios_fallidos_con_filtros(): void
    {
        $err1 = $this->envioConEstado(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        $err2 = $this->envioConEstado(SiiEnvioDte::ESTADO_RECHAZADO);
        $okEnviado = $this->envioConEstado(SiiEnvioDte::ESTADO_ENVIADO);  // NO debe aparecer
        $aceptado  = $this->envioConEstado(SiiEnvioDte::ESTADO_ACEPTADO); // NO debe aparecer

        Artisan::call('sii:listar-envios-fallidos');
        $output = Artisan::output();

        $this->assertStringContainsString((string) $err1->id, $output);
        $this->assertStringContainsString((string) $err2->id, $output);
        $this->assertStringNotContainsString('  ' . $okEnviado->id . '  ', $output);
        // Total fallidos = 2
        $this->assertStringContainsString('Total fallidos: 2', $output);
    }

    public function test_resumen_final_categoriza_por_tipo_de_error(): void
    {
        $this->envioConEstado(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        $this->envioConEstado(SiiEnvioDte::ESTADO_ERROR_TRANSPORTE);
        $this->envioConEstado(SiiEnvioDte::ESTADO_ERROR_PERMANENTE);
        $this->envioConEstado(SiiEnvioDte::ESTADO_RECHAZADO);
        $this->envioConEstado(SiiEnvioDte::ESTADO_ERROR_TIMEOUT);

        Artisan::call('sii:listar-envios-fallidos');
        $output = Artisan::output();

        $this->assertStringContainsString('Total fallidos: 5', $output);
        $this->assertStringContainsString('ERROR_TRANSPORTE: 2', $output);
        $this->assertStringContainsString('ERROR_PERMANENTE: 1', $output);
        $this->assertStringContainsString('RECHAZADO: 1', $output);
        $this->assertStringContainsString('ERROR_TIMEOUT: 1', $output);
    }
}
