<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\TasaPpmPropyme;
use App\Domains\Contabilidad\Services\Dj\Dj1947Service;
use App\Domains\Contabilidad\Services\Propyme\ResultadoTributarioPropymeService;
use App\Domains\Core\Models\Propietario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1947ConstruirTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected Dj1947Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = new Dj1947Service(new ResultadoTributarioPropymeService());

        TasaPpmPropyme::create([
            'anio'                   => 2026,
            'tasa_base_pct'          => 0.125,
            'tasa_sobre_50000uf_pct' => 0.250,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function crearEmpresa14D8(array $extra = [])
    {
        return $this->crearEmpresa(array_merge(['regimen_tributario' => '14_D8'], $extra));
    }

    private function insertarDte(int $empresaId, int $tipoDte, int $folio, string $fechaEmision, float $montoNeto, string $estado = 'ACEPTADO'): void
    {
        DB::table('sii_dte_emitido')->insert([
            'empresa_id'              => $empresaId,
            'tipo_dte'                => $tipoDte,
            'folio'                   => $folio,
            'fecha_emision'           => $fechaEmision,
            'estado'                  => $estado,
            'monto_neto'              => $montoNeto,
            'monto_exento'            => 0,
            'monto_total'             => $montoNeto * 1.19,
            'emisor_rut'              => '76000001-K',
            'emisor_razon_social'     => 'Empresa Test SA',
            'receptor_rut'            => '11111111-1',
            'receptor_razon_social'   => 'Cliente Test',
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }

    private function crearPropietario(int $empresaId, string $rut, string $nombre, float $pct): Propietario
    {
        return Propietario::withoutGlobalScopes()->create([
            'empresa_id'               => $empresaId,
            'rut'                      => $rut,
            'nombre'                   => $nombre,
            'porcentaje_participacion' => $pct,
        ]);
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    public function test_construye_una_linea_por_propietario(): void
    {
        $empresa = $this->crearEmpresa14D8();

        $this->insertarDte($empresa->id, 33, 1, '2026-04-15', 1_000_000.0);

        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 60.0);
        $this->crearPropietario($empresa->id, '22222222-2', 'Socio B', 40.0);

        $data = $this->service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);
    }

    public function test_distribuye_base_y_ppm_segun_participacion(): void
    {
        $empresa = $this->crearEmpresa14D8();

        // Ingresos: 1.000.000; sin gastos → base_imponible = 1.000.000
        $this->insertarDte($empresa->id, 33, 1, '2026-04-01', 1_000_000.0);

        $this->crearPropietario($empresa->id, '11111111-1', 'Socio A', 60.0);
        $this->crearPropietario($empresa->id, '22222222-2', 'Socio B', 40.0);

        $data = $this->service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);

        $camposA = collect($data->lineas)
            ->first(fn ($l) => $l->campos['rut_propietario'] === '11111111-1')
            ->campos;

        $camposB = collect($data->lineas)
            ->first(fn ($l) => $l->campos['rut_propietario'] === '22222222-2')
            ->campos;

        // Base 1.000.000; 60% = 600.000; 40% = 400.000
        $this->assertEquals(600_000, $camposA['base_atribuida']);
        $this->assertEquals(400_000, $camposB['base_atribuida']);
    }

    public function test_lanza_error_si_empresa_no_es_14d8(): void
    {
        $empresa = $this->crearEmpresa(['regimen_tributario' => '14_D3']);

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/14 D N°8/');

        $this->service->construir($empresa->id, 2026);
    }

    public function test_lanza_error_sin_propietarios(): void
    {
        $empresa = $this->crearEmpresa14D8();

        // Sin propietarios registrados
        $this->insertarDte($empresa->id, 33, 1, '2026-03-10', 500_000.0);

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/propietarios/');

        $this->service->construir($empresa->id, 2026);
    }
}
