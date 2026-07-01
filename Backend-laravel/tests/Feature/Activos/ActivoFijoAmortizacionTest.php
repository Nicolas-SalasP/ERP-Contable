<?php

namespace Tests\Feature\Activos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Activos\Models\ActivoFijo;

class ActivoFijoAmortizacionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->empresa = Empresa::create([
            'rut' => '77.888.888-8',
            'razon_social' => 'Empresa Amort Test',
            'regimen_tributario' => '14_D3',
            'tasa_impuesto' => 25.00,
        ]);
        $this->usuario = User::create([
            'nombre' => 'Tester Amort',
            'email' => 'amort@erp.cl',
            'password' => bcrypt('123'),
            'empresa_id' => $this->empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
    }

    private function crearActivo(array $extra = []): ActivoFijo
    {
        return ActivoFijo::create(array_merge([
            'empresa_id'            => $this->empresa->id,
            'codigo'                => 'AF-TEST-01',
            'nombre'                => 'Notebook Test',
            'valor_adquisicion'     => 1000000,
            'fecha_adquisicion'     => '2024-01-15',
            'vida_util_meses'       => 12,
            'valor_residual'        => 1,
            'depreciacion_acumulada'=> 0,
            'estado'                => 'ACTIVO',
        ], $extra));
    }

    public function test_retorna_tabla_completa(): void
    {
        $activo = $this->crearActivo();

        $response = $this->actingAs($this->usuario)
            ->getJson("/api/activos/{$activo->id}/amortizacion");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertArrayHasKey('resumen', $data);
        $this->assertArrayHasKey('filas', $data);
        $this->assertCount(12, $data['filas']);

        // Primer mes: período es 2024-02
        $this->assertEquals(1, $data['filas'][0]['numero_mes']);
        $this->assertEquals('2024-02', $data['filas'][0]['periodo']);

        // Último mes: número 12
        $this->assertEquals(12, $data['filas'][11]['numero_mes']);
        $this->assertEquals('2024-02', $data['filas'][0]['periodo']);

        // Resumen tiene los campos esperados
        $resumen = $data['resumen'];
        $this->assertEquals(12, $resumen['total_meses']);
        $this->assertEquals(1000000, $resumen['valor_adquisicion']);
        $this->assertEquals(1, $resumen['valor_residual']);
    }

    public function test_ultima_cuota_absorbe_redondeo(): void
    {
        // montoDepreciable = 1000000 - 1 = 999999
        // cuotaBase = round(999999/12) = round(83333.25) = 83333
        // 11 cuotas * 83333 = 916663; última cuota = 999999 - 916663 = 83336
        $activo = $this->crearActivo();

        $response = $this->actingAs($this->usuario)
            ->getJson("/api/activos/{$activo->id}/amortizacion");

        $response->assertStatus(200);
        $filas = $response->json('data.filas');

        $sumaTotal = array_sum(array_column($filas, 'cuota'));
        $montoDepreciable = 1000000 - 1; // valor_adquisicion - valor_residual

        $this->assertEquals($montoDepreciable, $sumaTotal, 'La suma de cuotas debe igualar exactamente el monto depreciable.');

        // Valor libro en última fila debe ser valor_residual
        $ultimaFila = end($filas);
        $this->assertEquals(1, $ultimaFila['valor_libro'], 'El valor libro final debe ser igual al valor residual.');
    }

    public function test_ya_ejecutado_marcado_correctamente(): void
    {
        // cuotaBase = round(999999/12) = 83333
        // Con 3 meses ejecutados: depreciacion_real = 3 * 83333 = 249999
        $cuotaBase = (int) round((1000000 - 1) / 12, 0);
        $depreciacionTresMeses = $cuotaBase * 3;

        $activo = $this->crearActivo([
            'depreciacion_acumulada' => $depreciacionTresMeses,
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson("/api/activos/{$activo->id}/amortizacion");

        $response->assertStatus(200);
        $filas = $response->json('data.filas');

        // Los primeros 3 meses deben ser ya_ejecutado=true
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($filas[$i]['ya_ejecutado'], "Mes " . ($i + 1) . " debe estar ejecutado.");
        }

        // El mes 4 en adelante debe ser ya_ejecutado=false
        for ($i = 3; $i < 12; $i++) {
            $this->assertFalse($filas[$i]['ya_ejecutado'], "Mes " . ($i + 1) . " no debe estar ejecutado.");
        }
    }

    public function test_activo_de_otra_empresa_retorna_404(): void
    {
        $otraEmpresa = Empresa::create([
            'rut' => '88.999.999-9',
            'razon_social' => 'Otra Empresa',
            'regimen_tributario' => '14_D3',
            'tasa_impuesto' => 25.00,
        ]);

        $activoOtraEmpresa = ActivoFijo::create([
            'empresa_id'            => $otraEmpresa->id,
            'codigo'                => 'AF-OTRA-01',
            'nombre'                => 'Activo Externo',
            'valor_adquisicion'     => 500000,
            'fecha_adquisicion'     => '2024-01-01',
            'vida_util_meses'       => 24,
            'valor_residual'        => 1,
            'depreciacion_acumulada'=> 0,
            'estado'                => 'ACTIVO',
        ]);

        $response = $this->actingAs($this->usuario)
            ->getJson("/api/activos/{$activoOtraEmpresa->id}/amortizacion");

        $response->assertStatus(404);
    }

    public function test_sin_autenticacion_retorna_401(): void
    {
        $activo = $this->crearActivo();

        $response = $this->getJson("/api/activos/{$activo->id}/amortizacion");

        $response->assertStatus(401);
    }
}
