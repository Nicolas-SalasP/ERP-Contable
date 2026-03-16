<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ContabilidadService;
use Faker\Factory as Faker;

class ContabilidadTest extends TestCase {
    
    private $faker;
    private $contabilidadService;

    protected function setUp(): void {
        parent::setUp();
        $this->faker = Faker::create('es_CL');
        $this->contabilidadService = new ContabilidadService();
    }

    public function test_asiento_contable_mantiene_equilibrio_al_crear_factura_masiva() {
        $montoNeto = $this->faker->randomFloat(0, 10000, 5000000);
        $iva = round($montoNeto * 0.19);
        $total = $montoNeto + $iva;
        
        $asientoSimulado = [
            ['cuenta_id' => 101, 'debe' => 0, 'haber' => $montoNeto], // Ingreso
            ['cuenta_id' => 201, 'debe' => 0, 'haber' => $iva],       // IVA Débito
            ['cuenta_id' => 105, 'debe' => $total, 'haber' => 0]      // Cliente (Por cobrar)
        ];

        // 2. Ejecución de la prueba de integridad
        $sumaDebe = array_sum(array_column($asientoSimulado, 'debe'));
        $sumaHaber = array_sum(array_column($asientoSimulado, 'haber'));

        // 3. Validación con nuestro formateador estricto
        $this->assertEquilibrioContable(
            $sumaDebe, 
            $sumaHaber, 
            'ContabilidadTest.php', 
            __LINE__
        );
    }
}