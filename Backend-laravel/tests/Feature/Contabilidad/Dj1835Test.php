<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Contabilidad\DataTransfer\DjData;
use App\Domains\Contabilidad\DataTransfer\DjLineaData;
use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Services\Dj\Dj1835Service;
use App\Domains\Core\Models\Pais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class Dj1835Test extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    private function crearProveedorExterior(int $empresaId, string $rut = '12-3456789', string $razonSocial = 'Acme Corp LLC'): Proveedor
    {
        Pais::firstOrCreate(['iso' => 'US'], ['nombre' => 'Estados Unidos', 'moneda_defecto' => 'USD', 'activo' => true]);

        return Proveedor::create([
            'empresa_id' => $empresaId,
            'rut' => $rut,
            'razon_social' => $razonSocial,
            'pais_iso' => 'US',
            'moneda_defecto' => 'USD',
            'codigo_interno' => 'PEXT-'.uniqid(),
        ]);
    }

    private function crearFacturaExteriorConRetencion(
        int $empresaId,
        int $proveedorId,
        int $montoBruto,
        float $retencion,
        string $tipoGasto = 'servicios_tecnicos',
        string $fecha = '2026-06-15'
    ): Factura {
        return Factura::create([
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId,
            'numero_factura' => uniqid('FE-'),
            'codigo_unico' => Factura::generarCodigoUnico(),
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => $fecha,
            'monto_bruto' => $montoBruto,
            'monto_neto' => $montoBruto,
            'monto_iva' => 0,
            'es_documento_exterior' => true,
            'tipo_gasto_art59' => $tipoGasto,
            'retencion_art59' => $retencion,
            'estado' => 'REGISTRADA',
            'moneda' => 'USD',
            'tipo_cambio' => 950,
        ]);
    }

    public function test_construye_una_linea_por_proveedor_y_tipo_gasto(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 1_000_000, 150_000, 'servicios_tecnicos');

        $service = new Dj1835Service;
        $data = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);
    }

    public function test_agrupa_facturas_del_mismo_proveedor_y_tipo(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 500_000, 75_000, 'regalias', '2026-03-01');
        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 300_000, 90_000, 'regalias', '2026-07-15');

        $service = new Dj1835Service;
        $data = $service->construir($empresa->id, 2026);

        $this->assertCount(1, $data->lineas);
        $campos = $data->lineas[0]->campos;
        $this->assertEquals(800_000, $campos['monto_bruto_total']);
        $this->assertEquals(165_000, $campos['retencion_total']);
        $this->assertEquals(2, $campos['num_documentos']);
    }

    public function test_genera_lineas_distintas_por_tipo_gasto_del_mismo_proveedor(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 500_000, 75_000, 'regalias');
        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 200_000, 30_000, 'servicios_tecnicos');

        $service = new Dj1835Service;
        $data = $service->construir($empresa->id, 2026);

        $this->assertCount(2, $data->lineas);
    }

    public function test_excluye_facturas_sin_retencion_art59(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        // Factura exterior SIN retención — no debe incluirse
        Factura::create([
            'empresa_id' => $empresa->id,
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'FE-NORET',
            'codigo_unico' => Factura::generarCodigoUnico(),
            'tipo_documento' => 'FACTURA',
            'fecha_emision' => '2026-04-01',
            'monto_bruto' => 800_000,
            'monto_neto' => 800_000,
            'monto_iva' => 0,
            'es_documento_exterior' => true,
            'retencion_art59' => 0,
            'estado' => 'REGISTRADA',
            'moneda' => 'USD',
            'tipo_cambio' => 950,
        ]);

        $this->expectException(DjException::class);

        $service = new Dj1835Service;
        $service->construir($empresa->id, 2026);
    }

    public function test_excluye_facturas_anuladas(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $factura = $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 500_000, 75_000);
        $factura->update(['estado' => 'ANULADA']);

        $this->expectException(DjException::class);

        $service = new Dj1835Service;
        $service->construir($empresa->id, 2026);
    }

    public function test_lanza_error_si_no_hay_facturas_exterior_con_retencion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $this->expectException(DjException::class);
        $this->expectExceptionMessageMatches('/Art\. 59/');

        $service = new Dj1835Service;
        $service->construir($empresa->id, 2026);
    }

    public function test_valida_sin_errores_con_datos_correctos(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 1_000_000, 150_000);

        $service = new Dj1835Service;
        $data = $service->construir($empresa->id, 2026);
        $errores = $service->validar($data);

        $this->assertEmpty($errores);
    }

    public function test_detecta_retencion_total_cero_en_validacion(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();

        $data = new DjData(
            codigoDj: '1835',
            empresaId: $empresa->id,
            anio: 2026,
            cabecera: ['rut_empresa' => $empresa->rut, 'razon_social' => $empresa->razon_social],
            lineas: [
                new DjLineaData([
                    'rut_proveedor' => '12-3456789',
                    'razon_social' => 'Acme Corp',
                    'tipo_gasto' => 'servicios_tecnicos',
                    'monto_bruto_total' => 500_000,
                    'retencion_total' => 0,
                    'num_documentos' => 1,
                ]),
            ],
        );

        $service = new Dj1835Service;
        $errores = $service->validar($data);

        $this->assertNotEmpty($errores);
        $this->assertTrue(collect($errores)->contains(fn ($e) => str_contains($e, 'retención Art. 59 total es cero')));
    }

    public function test_archivo_contiene_registros_a_d_t(): void
    {
        [$empresa] = $this->crearEmpresaConAdmin();
        $proveedor = $this->crearProveedorExterior($empresa->id);

        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 800_000, 120_000);

        $service = new Dj1835Service;
        $data = $service->construir($empresa->id, 2026);
        $archivo = $service->formatearArchivo($data);

        $this->assertTrue(str_starts_with($archivo, 'A;'));
        $this->assertStringContainsString("\nD;", $archivo);
        $this->assertStringContainsString("\nT;", $archivo);
    }

    public function test_multitenant_no_cruza_empresas(): void
    {
        [$empresa1] = $this->crearEmpresaConAdmin();
        [$empresa2] = $this->crearEmpresaConAdmin();

        $proveedor = $this->crearProveedorExterior($empresa1->id);
        $this->crearFacturaExteriorConRetencion($empresa1->id, $proveedor->id, 500_000, 75_000);

        $this->expectException(DjException::class);

        $service = new Dj1835Service;
        $service->construir($empresa2->id, 2026);
    }

    private function crearEmpresaConSuperAdmin(): array
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $usuario->update(['rol_id' => $this->rolSuperAdmin->id]);

        return [$empresa, $usuario];
    }

    // HTTP layer ----------------------------------------------------------

    public function test_http_generar_sin_facturas_exterior_con_retencion_devuelve_422(): void
    {
        [, $usuario] = $this->crearEmpresaConSuperAdmin();

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1835/generar', ['anio' => 2026]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_http_generar_con_factura_exterior_devuelve_201(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConSuperAdmin();

        $proveedor = $this->crearProveedorExterior($empresa->id);
        $this->crearFacturaExteriorConRetencion($empresa->id, $proveedor->id, 800_000, 120_000);

        $response = $this->actingAs($usuario)
            ->postJson('/api/dj/1835/generar', ['anio' => 2026]);

        $response->assertStatus(201)
            ->assertJsonPath('data.cantidad_registros', 1);
    }

    public function test_http_multitenant_usuario_solo_ve_sus_envios(): void
    {
        [$empresa1, $usuario1] = $this->crearEmpresaConSuperAdmin();
        [, $usuario2] = $this->crearEmpresaConSuperAdmin();

        $proveedor = $this->crearProveedorExterior($empresa1->id);
        $this->crearFacturaExteriorConRetencion($empresa1->id, $proveedor->id, 600_000, 90_000);
        $this->actingAs($usuario1)->postJson('/api/dj/1835/generar', ['anio' => 2026]);

        $response = $this->actingAs($usuario2)->getJson('/api/dj/1835');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
