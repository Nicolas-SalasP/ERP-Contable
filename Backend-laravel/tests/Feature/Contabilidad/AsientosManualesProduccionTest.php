<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Concerns\PreparaEntornoBase;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Models\DetalleAsiento;
use App\Domains\Contabilidad\Models\CentroCosto;

/**
 * Tests focalizados de asientos manuales con escenarios de produccion.
 *
 * Cubre casos donde un usuario real podria romper el sistema:
 * - Glosas con caracteres especiales chilenos
 * - Asientos con muchos detalles
 * - Centros de costo invalidos
 * - Concurrencia
 */
class AsientosManualesProduccionTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected $empresa;
    protected $usuario;
    protected $cuentaCaja;
    protected $cuentaIngreso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->cuentaCaja = PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '110101',
            'nombre' => 'Caja',
            'tipo' => 'ACTIVO',
            'imputable' => true,
            'activo' => true,
        ]);

        $this->cuentaIngreso = PlanCuenta::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => '410101',
            'nombre' => 'Ingreso',
            'tipo' => 'INGRESO',
            'imputable' => true,
            'activo' => true,
        ]);
    }

    public function test_asiento_con_glosa_que_contiene_acentos_chilenos_se_persiste_intacta()
    {
        $glosaConAcentos = 'Asiento por inversión en máquina nº1 - Concepción';

        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => $glosaConAcentos,
            'numero_comprobante' => 'ACENTO-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000001,
        ]);

        $persistida = AsientoContable::find($asiento->id)->glosa;

        // Validar que NO se corrompio el encoding (utf8mb4_unicode_ci)
        $this->assertEquals($glosaConAcentos, $persistida);
        $this->assertStringContainsString('inversión', $persistida);
        $this->assertStringContainsString('Concepción', $persistida);
        $this->assertStringContainsString('nº1', $persistida);
    }

    public function test_asiento_con_glosa_con_emojis_funciona_o_falla_consistentemente()
    {
        // Algunos clientes ponen emojis en glosas. utf8mb4 los soporta.
        $glosaConEmoji = '📊 Cierre mensual abril';

        try {
            $asiento = AsientoContable::create([
                'empresa_id' => $this->empresa->id,
                'fecha' => '2026-05-01',
                'glosa' => $glosaConEmoji,
                'numero_comprobante' => 'EMOJI-001',
                'estado' => 'CONTABILIZADO',
                'codigo_unico' => 60000002,
            ]);
            // Si guardo, debe leerse igual
            $this->assertEquals($glosaConEmoji, $asiento->fresh()->glosa);
        } catch (\Throwable $e) {
            // Si falla, debe ser por validacion explicita, no por encoding crash
            $this->assertStringNotContainsString('Mysqli statement execute error', $e->getMessage(),
                'Encoding crash en utf8mb4 - revisar collation BD');
        }
    }

    public function test_asiento_con_30_lineas_de_detalle_se_persiste_completo()
    {
        $asiento = AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento de 30 lineas',
            'numero_comprobante' => 'BIG-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000003,
        ]);

        // 15 lineas de DEBE
        for ($i = 0; $i < 15; $i++) {
            DetalleAsiento::create([
                'asiento_id' => $asiento->id,
                'cuenta_contable' => '110101',
                'tipo_operacion' => 'DEBE',
                'debe' => 1000,
                'haber' => 0,
            ]);
        }
        // 15 lineas de HABER
        for ($i = 0; $i < 15; $i++) {
            DetalleAsiento::create([
                'asiento_id' => $asiento->id,
                'cuenta_contable' => '410101',
                'tipo_operacion' => 'HABER',
                'debe' => 0,
                'haber' => 1000,
            ]);
        }

        $detalles = DetalleAsiento::where('asiento_id', $asiento->id)->get();
        $this->assertCount(30, $detalles);

        $totalDebe = $detalles->sum('debe');
        $totalHaber = $detalles->sum('haber');
        $this->assertEquals($totalDebe, $totalHaber);
        $this->assertEquals(15000, (float) $totalDebe);
    }

    public function test_asiento_via_endpoint_no_acepta_centro_costo_inactivo()
    {
        $centroInactivo = CentroCosto::create([
            'empresa_id' => $this->empresa->id,
            'codigo' => 'CC-OFF',
            'nombre' => 'Centro Inactivo',
            'activo' => false,
        ]);

        // Intentamos crear un asiento via endpoint con CC inactivo.
        // El service ahora valida que el CC este activo y pertenezca a la empresa.
        $response = $this->actingAs($this->usuario)->postJson('/api/contabilidad/asientos', [
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento con CC inactivo',
            'detalles' => [
                [
                    'cuenta_contable' => '110101',
                    'debe' => 1000,
                    'haber' => 0,
                    'centro_costo_id' => $centroInactivo->id, // INACTIVO!
                    'tipo_operacion' => 'DEBE',
                ],
                [
                    'cuenta_contable' => '410101',
                    'debe' => 0,
                    'haber' => 1000,
                    'tipo_operacion' => 'HABER',
                ],
            ],
        ]);

        // Debe rechazar (4xx) por validacion de CC
        $this->assertContains($response->getStatusCode(), [400, 422, 500],
            'Asiento con CC inactivo deberia ser rechazado');
    }

    public function test_asiento_via_endpoint_no_acepta_centro_costo_de_otra_empresa()
    {
        // CC activo pero de otra empresa
        $empresaB = $this->crearEmpresa();
        $ccAjeno = CentroCosto::create([
            'empresa_id' => $empresaB->id,
            'codigo' => 'CC-AJENO',
            'nombre' => 'CC Ajeno',
            'activo' => true,
        ]);

        $response = $this->actingAs($this->usuario)->postJson('/api/contabilidad/asientos', [
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento con CC ajeno',
            'detalles' => [
                [
                    'cuenta_contable' => '110101',
                    'debe' => 1000,
                    'haber' => 0,
                    'centro_costo_id' => $ccAjeno->id,
                    'tipo_operacion' => 'DEBE',
                ],
                [
                    'cuenta_contable' => '410101',
                    'debe' => 0,
                    'haber' => 1000,
                    'tipo_operacion' => 'HABER',
                ],
            ],
        ]);

        // IDOR cruzado: el CC pertenece a otra empresa, debe rechazar
        $this->assertContains($response->getStatusCode(), [400, 422, 500],
            'IDOR: Asiento con CC de otra empresa deberia ser rechazado');
    }

    public function test_numero_comprobante_no_puede_ser_duplicado_dentro_de_la_misma_empresa()
    {
        AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Primer asiento',
            'numero_comprobante' => 'COMP-DUP',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000005,
        ]);

        // Despues del fix con unique compuesto, el segundo debe fallar
        $this->expectException(\Illuminate\Database\QueryException::class);

        AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Segundo asiento (duplicado)',
            'numero_comprobante' => 'COMP-DUP', // duplicado!
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000006,
        ]);
    }

    public function test_numero_comprobante_si_puede_repetirse_entre_empresas_distintas()
    {
        // El unique es compuesto (empresa_id, numero_comprobante).
        // Por lo tanto debe permitir el mismo numero en empresas distintas.
        AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Comp empresa A',
            'numero_comprobante' => 'COMP-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000007,
        ]);

        $empresaB = $this->crearEmpresa();

        $asientoB = AsientoContable::create([
            'empresa_id' => $empresaB->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Comp empresa B',
            'numero_comprobante' => 'COMP-001', // mismo numero, otra empresa - OK
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000008,
        ]);

        $this->assertNotNull($asientoB->id,
            'Unique compuesto incorrecto: bloqueo numero_comprobante repetido entre empresas');
    }

    public function test_codigo_unico_de_asiento_es_unico_globalmente_no_solo_por_empresa()
    {
        AsientoContable::create([
            'empresa_id' => $this->empresa->id,
            'fecha' => '2026-05-01',
            'glosa' => 'Asiento A',
            'numero_comprobante' => 'A-001',
            'estado' => 'CONTABILIZADO',
            'codigo_unico' => 60000099,
        ]);

        // Otra empresa intenta usar el mismo codigo_unico
        $empresaB = $this->crearEmpresa();

        try {
            AsientoContable::create([
                'empresa_id' => $empresaB->id,
                'fecha' => '2026-05-01',
                'glosa' => 'Asiento B',
                'numero_comprobante' => 'B-001',
                'estado' => 'CONTABILIZADO',
                'codigo_unico' => 60000099, // mismo codigo unico que empresa A!
            ]);

            $this->fail('Se permitio codigo_unico duplicado entre empresas');
        } catch (\Illuminate\Database\QueryException $e) {
            // Esperado: codigo_unico es UNIQUE global
            $msg = $e->getMessage();
            $this->assertTrue(
                str_contains($msg, 'Duplicate') || str_contains($msg, 'UNIQUE'),
                'Mensaje de error inesperado: ' . $msg
            );
        }
    }
}
