<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Proveedor;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * generarNumeroOc() usa ContadorEmpresaService::siguienteNumero() (atomico, con lock) en vez de
 * MAX(numero_oc), codificando el anio en el tipo de contador ("orden_compra_{anio}") para
 * preservar el reinicio anual del correlativo.
 */
class OrdenCompraCorrelativoTest extends TestCase
{
    use PreparaEntornoBase, RefreshDatabase;

    protected Empresa $empresa;

    protected User $usuario;

    protected Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();

        [$this->empresa, $this->usuario] = $this->crearEmpresaConAdmin();

        $this->proveedor = Proveedor::create([
            'empresa_id' => $this->empresa->id,
            'razon_social' => 'Proveedor Test SpA',
            'rut' => '76.543.210-5',
            'codigo_interno' => 'P-TEST-01',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);
    }

    private function payload(): array
    {
        return [
            'proveedor_id' => $this->proveedor->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [
                ['producto_descripcion' => 'Producto test', 'cantidad' => 1, 'precio_unitario' => 1000, 'subtotal' => 1000],
            ],
        ];
    }

    private function crearOc(): string
    {
        $response = $this->actingAs($this->usuario)
            ->postJson('/api/comercial/ordenes-compra', $this->payload());

        $response->assertStatus(201);

        return $response->json('data.numero_oc');
    }

    public function test_correlativo_es_secuencial_dentro_del_mismo_anio(): void
    {
        $numero1 = $this->crearOc();
        $numero2 = $this->crearOc();
        $numero3 = $this->crearOc();

        $anio = now()->year;
        $this->assertSame(sprintf('OC-%d-0001', $anio), $numero1);
        $this->assertSame(sprintf('OC-%d-0002', $anio), $numero2);
        $this->assertSame(sprintf('OC-%d-0003', $anio), $numero3);
    }

    public function test_correlativo_reinicia_en_0001_al_cambiar_de_anio(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 20));
        $numero2026a = $this->crearOc();
        $numero2026b = $this->crearOc();

        Carbon::setTestNow(Carbon::create(2027, 1, 5));
        $numero2027 = $this->crearOc();

        Carbon::setTestNow();

        $this->assertSame('OC-2026-0001', $numero2026a);
        $this->assertSame('OC-2026-0002', $numero2026b);
        $this->assertSame('OC-2027-0001', $numero2027);
    }

    public function test_correlativo_es_independiente_por_empresa(): void
    {
        [$otraEmpresa, $otroUsuario] = $this->crearEmpresaConAdmin();
        $otroProveedor = Proveedor::create([
            'empresa_id' => $otraEmpresa->id,
            'razon_social' => 'Otro Proveedor',
            'rut' => '65.432.109-4',
            'codigo_interno' => 'P-OTRO',
            'pais_iso' => 'CL',
            'moneda_defecto' => 'CLP',
        ]);

        $numeroEmpresaA = $this->crearOc();

        $respuestaB = $this->actingAs($otroUsuario)->postJson('/api/comercial/ordenes-compra', [
            'proveedor_id' => $otroProveedor->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'detalles' => [['producto_descripcion' => 'Prod B', 'cantidad' => 1, 'precio_unitario' => 500, 'subtotal' => 500]],
        ]);
        $respuestaB->assertStatus(201);
        $numeroEmpresaB = $respuestaB->json('data.numero_oc');

        $anio = now()->year;
        $this->assertSame(sprintf('OC-%d-0001', $anio), $numeroEmpresaA);
        $this->assertSame(sprintf('OC-%d-0001', $anio), $numeroEmpresaB);
    }

    public function test_migracion_de_datos_evita_colision_con_ocs_preexistentes(): void
    {
        $anio = now()->year;

        // Simula OCs preexistentes creadas directamente en BD (antes del fix),
        // sin pasar por ContadorEmpresaService, con numeracion alta.
        DB::table('ordenes_compra')->insert([
            [
                'empresa_id' => $this->empresa->id,
                'proveedor_id' => $this->proveedor->id,
                'numero_oc' => sprintf('OC-%d-0050', $anio),
                'fecha_emision' => now()->format('Y-m-d'),
                'estado' => 'BORRADOR',
                'moneda' => 'CLP',
                'tipo_cambio' => 1,
                'subtotal' => 0,
                'impuesto' => 0,
                'total' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'empresa_id' => $this->empresa->id,
                'proveedor_id' => $this->proveedor->id,
                'numero_oc' => sprintf('OC-%d-0049', $anio),
                'fecha_emision' => now()->format('Y-m-d'),
                'estado' => 'BORRADOR',
                'moneda' => 'CLP',
                'tipo_cambio' => 1,
                'subtotal' => 0,
                'impuesto' => 0,
                'total' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // No debe existir contador aun para este tipo: confirma el escenario
        // "post-migracion pendiente de sembrar".
        $this->assertDatabaseMissing('contadores_empresa', [
            'empresa_id' => $this->empresa->id,
            'tipo' => "orden_compra_{$anio}",
        ]);

        // La migracion de datos ya corrio (vacia) al momento del
        // RefreshDatabase inicial, antes de que existieran estas OCs
        // "preexistentes" simuladas. Se re-ejecuta su up() directamente
        // (sin pasar por el registro de migraciones ya corridas) para
        // reproducir el escenario real de "correr la migracion sobre datos
        // existentes".
        /** @var Migration $migracion */
        $migracion = require base_path(
            'database/migrations/2026_07_13_000001_seed_contador_orden_compra_desde_ordenes_existentes.php'
        );
        $migracion->up();

        $this->assertDatabaseHas('contadores_empresa', [
            'empresa_id' => $this->empresa->id,
            'tipo' => "orden_compra_{$anio}",
            'ultimo_valor' => 50,
        ]);

        $numeroNuevo = $this->crearOc();

        $this->assertSame(sprintf('OC-%d-0051', $anio), $numeroNuevo);
        $this->assertDatabaseHas('ordenes_compra', [
            'empresa_id' => $this->empresa->id,
            'numero_oc' => sprintf('OC-%d-0051', $anio),
        ]);
    }
}
