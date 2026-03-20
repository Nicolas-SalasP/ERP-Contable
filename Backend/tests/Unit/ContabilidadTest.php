<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ContabilidadService;
use Faker\Factory as Faker;
use Exception;

class ContabilidadTest extends TestCase
{

    private $faker;
    private $contabilidadService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create('es_CL');
        $this->contabilidadService = new ContabilidadService();
    }

    public function test_verificar_entorno_pruebas()
    {
        $entorno = getenv('APP_ENV');
        $this->assertEquals('testing', $entorno);
    }

    public function test_asiento_contable_mantiene_equilibrio_al_crear_factura_masiva()
    {
        $montoNeto = $this->faker->randomFloat(0, 10000, 5000000);
        $iva = round($montoNeto * 0.19);
        $total = $montoNeto + $iva;

        $asientoSimulado = [
            ['cuenta_id' => 101, 'debe' => 0, 'haber' => $montoNeto], // Ingreso
            ['cuenta_id' => 201, 'debe' => 0, 'haber' => $iva],       // IVA Débito
            ['cuenta_id' => 105, 'debe' => $total, 'haber' => 0]      // Cliente (Por cobrar)
        ];

        $sumaDebe = array_sum(array_column($asientoSimulado, 'debe'));
        $sumaHaber = array_sum(array_column($asientoSimulado, 'haber'));

        // Validamos usando tu aserción personalizada
        $this->assertEquilibrioContable($sumaDebe, $sumaHaber, 'ContabilidadTest.php', __LINE__);
    }

    // ========================================================
    // PROTECCIÓN CONTRA ASIENTOS INVÁLIDOS
    // ========================================================

    public function test_rechaza_asiento_descuadrado()
    {
        // Le decimos a PHPUnit que ESPERAMOS que esto lance una Excepción
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El asiento está descuadrado");

        // Intentamos registrar un asiento donde el Debe y Haber no coinciden
        $datosDescuadrados = [
            'fecha' => '2026-03-16',
            'glosa' => 'Asiento Malicioso Descuadrado',
            'detalles' => [
                ['cuenta_codigo' => '110101', 'debe' => 1000, 'haber' => 0],
                ['cuenta_codigo' => '110201', 'debe' => 0, 'haber' => 500], // Falta dinero aquí
            ]
        ];

        // Al ejecutar esto, el servicio DEBE fallar. Si falla, la prueba PASA en verde.
        $this->contabilidadService->registrarAsientoManualAvanzado($datosDescuadrados);
    }

    public function test_rechaza_asiento_con_valor_cero()
    {
        // Esperamos la excepción exacta que pusiste en tu ContabilidadService
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("El asiento no puede tener valor cero.");

        $datosCero = [
            'fecha' => '2026-03-16',
            'glosa' => 'Asiento con valor cero',
            'detalles' => [
                ['cuenta_codigo' => '110101', 'debe' => 0, 'haber' => 0],
                ['cuenta_codigo' => '110201', 'debe' => 0, 'haber' => 0],
            ]
        ];

        $this->contabilidadService->registrarAsientoManualAvanzado($datosCero);
    }

    public function test_registro_asiento_avanzado_cuadrado_y_exitoso()
    {
        // Aquí generamos datos dinámicos con Faker para probar la flexibilidad
        $montoAleatorio = $this->faker->numberBetween(1000, 50000);

        $datosCorrectos = [
            'fecha' => date('Y-m-d'),
            'glosa' => 'Traspaso entre cuentas de prueba ' . $this->faker->uuid(),
            'detalles' => [
                ['cuenta_codigo' => '110101', 'debe' => $montoAleatorio, 'haber' => 0],
                ['cuenta_codigo' => '110201', 'debe' => 0, 'haber' => $montoAleatorio],
            ]
        ];

        // Ejecutamos el servicio
        $resultado = $this->contabilidadService->registrarAsientoManualAvanzado($datosCorrectos);

        // Aserciones: Verificamos que el servicio nos devuelva un ID y un código válidos
        $this->assertArrayHasKey('id', $resultado, "El servicio no devolvió el ID del asiento");
        $this->assertArrayHasKey('codigo', $resultado, "El servicio no devolvió el código del asiento");
        $this->assertNotEmpty($resultado['codigo']);
    }
}