<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Services\AsientoContableService;
use App\Domains\Core\Services\ContadorEmpresaService;

class NumeroComprobantePorEmpresaTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresaA;
    protected $empresaB;
    protected $usuarioA;
    protected $usuarioB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresaA, $this->usuarioA] = $this->crearEmpresaConAdmin([], [
            'email' => 'admin-corr-a-' . uniqid() . '@test.cl'
        ]);
        [$this->empresaB, $this->usuarioB] = $this->crearEmpresaConAdmin([], [
            'email' => 'admin-corr-b-' . uniqid() . '@test.cl'
        ]);

        foreach ([$this->empresaA->id, $this->empresaB->id] as $empresaId) {
            PlanCuenta::create([
                'empresa_id' => $empresaId,
                'codigo' => '110101', 'nombre' => 'Caja', 'tipo' => 'ACTIVO',
                'imputable' => true, 'activo' => true,
            ]);
            PlanCuenta::create([
                'empresa_id' => $empresaId,
                'codigo' => '410101', 'nombre' => 'Ingreso', 'tipo' => 'INGRESO',
                'imputable' => true, 'activo' => true,
            ]);
        }
    }

    private function crearYDevolverNumero(int $empresaId, int $usuarioId): string
    {
        $service = app(AsientoContableService::class);
        $asiento = $service->crearAsientoManual([
            'empresa_id' => $empresaId,
            'usuario_id' => $usuarioId,
            'fecha' => '2026-05-15',
            'glosa' => 'Test',
            'tipo' => 'traspaso',
            'detalles' => [
                ['cuenta_contable' => '110101', 'debe' => 1000, 'haber' => 0, 'tipo_operacion' => 'DEBE'],
                ['cuenta_contable' => '410101', 'debe' => 0, 'haber' => 1000, 'tipo_operacion' => 'HABER'],
            ],
        ]);
        return $asiento->fresh()->numero_comprobante;
    }

    private function extraerCorrelativo(string $numeroComprobante): int
    {
        return (int) substr($numeroComprobante, -6);
    }

    public function test_empresa_a_arranca_correlativo_en_1()
    {
        $numero = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $this->assertEquals(1, $this->extraerCorrelativo($numero),
            "Primer asiento debe tener correlativo 1. Fue: {$numero}");
    }

    public function test_empresa_a_lleva_secuencia_1_2_3_4_5()
    {
        $correlativos = [];
        for ($i = 0; $i < 5; $i++) {
            $numero = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
            $correlativos[] = $this->extraerCorrelativo($numero);
        }
        $this->assertEquals([1, 2, 3, 4, 5], $correlativos);
    }

    public function test_empresa_b_arranca_en_1_aunque_a_tenga_asientos()
    {
        for ($i = 0; $i < 3; $i++) {
            $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        }

        $numeroB = $this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id);
        $correlativoB = $this->extraerCorrelativo($numeroB);

        $this->assertEquals(1, $correlativoB,
            "Empresa B deberia arrancar en 1 independiente. Fue: {$numeroB}");
    }

    public function test_dos_empresas_tienen_mismo_correlativo_para_su_primer_asiento()
    {
        $numA = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $numB = $this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id);

        $corrA = $this->extraerCorrelativo($numA);
        $corrB = $this->extraerCorrelativo($numB);

        $this->assertEquals(1, $corrA);
        $this->assertEquals(1, $corrB);
        $this->assertEquals($corrA, $corrB,
            "Ambas empresas deberian tener correlativo 1 en su primer asiento");
    }

    public function test_secuencias_intercaladas_no_se_mezclan()
    {
        $a1 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id));
        $b1 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id));
        $a2 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id));
        $b2 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id));
        $a3 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id));
        $b3 = $this->extraerCorrelativo($this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id));

        $this->assertEquals([1, 2, 3], [$a1, $a2, $a3], "Empresa A deberia tener 1,2,3");
        $this->assertEquals([1, 2, 3], [$b1, $b2, $b3], "Empresa B deberia tener 1,2,3");
    }

    public function test_contador_persiste_estado_y_se_recupera_por_empresa()
    {
        $contadorService = app(ContadorEmpresaService::class);

        $this->assertEquals(0,
            $contadorService->ultimoNumeroAsignado($this->empresaA->id, 'asiento_comprobante')
        );

        $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);

        $this->assertEquals(3,
            $contadorService->ultimoNumeroAsignado($this->empresaA->id, 'asiento_comprobante')
        );

        $this->assertEquals(0,
            $contadorService->ultimoNumeroAsignado($this->empresaB->id, 'asiento_comprobante')
        );
    }

    public function test_correlativo_no_retrocede_aunque_se_borre_un_asiento()
    {
        $service = app(AsientoContableService::class);

        $asientos = [];
        for ($i = 0; $i < 3; $i++) {
            $asientos[] = $service->crearAsientoManual([
                'empresa_id' => $this->empresaA->id,
                'usuario_id' => $this->usuarioA->id,
                'fecha' => '2026-05-15',
                'glosa' => "A{$i}",
                'tipo' => 'traspaso',
                'detalles' => [
                    ['cuenta_contable' => '110101', 'debe' => 1000, 'haber' => 0, 'tipo_operacion' => 'DEBE'],
                    ['cuenta_contable' => '410101', 'debe' => 0, 'haber' => 1000, 'tipo_operacion' => 'HABER'],
                ],
            ]);
        }

        DB::table('asientos_contables')->where('id', end($asientos)->id)->delete();
        $nuevoNumero = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $this->assertEquals(4, $this->extraerCorrelativo($nuevoNumero),
            "Despues de eliminar correlativo 3, el siguiente debe ser 4 (no se reusa). Fue: {$nuevoNumero}");
    }

    public function test_formato_numero_comprobante_es_aatc_seis_digitos()
    {
        $numero = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);

        $this->assertEquals(10, strlen($numero),
            "numero_comprobante debe tener 10 caracteres. Fue: '{$numero}'");

        $this->assertMatchesRegularExpression('/^\d{10}$/', $numero,
            "Solo digitos. Fue: '{$numero}'");

        $this->assertEquals('000001', substr($numero, -6),
            "Correlativo 1 = '000001'. Fue: '{$numero}'");
    }

    public function test_unique_compuesto_permite_mismo_numero_entre_empresas()
    {
        $numA = $this->crearYDevolverNumero($this->empresaA->id, $this->usuarioA->id);
        $numB = $this->crearYDevolverNumero($this->empresaB->id, $this->usuarioB->id);

        $this->assertEquals(
            $this->extraerCorrelativo($numA),
            $this->extraerCorrelativo($numB),
            "Ambas empresas deben tener correlativo 1 en su primer asiento"
        );

        $cantidadConMismoNumero = AsientoContable::where('numero_comprobante', $numA)->count();
        $this->assertEquals(2, $cantidadConMismoNumero,
            "Deben existir 2 asientos con el mismo numero_comprobante (uno por empresa)");
    }
}
