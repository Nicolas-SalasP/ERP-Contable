<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Contabilidad\Exceptions\ContabilidadException;

/**
 * Regresión: reversar un asiento (AsientoContableService::procesarReversa,
 * usado por reversarAsiento/reversarAsientoPorId) debe marcar el asiento
 * original como ANULADO. Antes del fix quedaba en MAYORIZADO para siempre,
 * lo que dejaba guards de idempotencia (ej. F29) bloqueados y hacía que
 * reportes (Libro Mayor/Balance) siguieran tratándolo como vigente.
 */
class ReversaAsientoTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected AsientoContableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();
        $this->service = app(AsientoContableService::class);

        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '110101',
            'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'imputable' => true, 'activo' => true,
        ]);
        PlanCuenta::create([
            'empresa_id' => $this->empresa->id, 'codigo' => '410101',
            'nombre' => 'Ingreso', 'tipo' => 'INGRESO', 'imputable' => true, 'activo' => true,
        ]);
    }

    private function crearAsientoBase()
    {
        return $this->service->registrarAsiento([
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->usuario->id,
            'fecha' => now()->toDateString(),
            'glosa' => 'Asiento de prueba',
            'tipo_asiento' => 'traspaso',
            'estado' => 'MAYORIZADO',
        ], [
            ['cuenta_contable' => '110101', 'debe' => 1000, 'haber' => 0],
            ['cuenta_contable' => '410101', 'debe' => 0, 'haber' => 1000],
        ]);
    }

    public function test_reversar_asiento_marca_el_original_como_anulado()
    {
        $asiento = $this->crearAsientoBase();

        $this->service->reversarAsientoPorId(
            $this->empresa->id, $this->usuario->id, $asiento->id, now()->toDateString(), 'Corrección de prueba'
        );

        $this->assertEquals('ANULADO', $asiento->fresh()->estado);
    }

    public function test_no_se_puede_reversar_dos_veces_el_mismo_asiento()
    {
        $asiento = $this->crearAsientoBase();

        $this->service->reversarAsientoPorId(
            $this->empresa->id, $this->usuario->id, $asiento->id, now()->toDateString(), 'Primera reversa'
        );

        $this->expectException(ContabilidadException::class);
        $this->service->reversarAsientoPorId(
            $this->empresa->id, $this->usuario->id, $asiento->id, now()->toDateString(), 'Segunda reversa'
        );
    }
}
