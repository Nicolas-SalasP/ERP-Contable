<?php

namespace Tests\Feature\Contabilidad;

use App\Domains\Contabilidad\Exceptions\PeriodoCerradoException;
use App\Domains\Contabilidad\Models\AsientoContable;
use App\Domains\Contabilidad\Services\PeriodoContableService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Cierra F-1/F-2 de la auditoria: inmutabilidad de periodos contables cerrados.
 */
class BloqueoPeriodoContableTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    private PeriodoContableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        $this->service = app(PeriodoContableService::class);
    }

    private function nuevoAsiento(int $empresaId, string $fecha): AsientoContable
    {
        return AsientoContable::create([
            // codigo_unico es unsignedBigInteger nullable; en producción los asientos
            // no lo setean (AsientoContableService nunca lo escribe). MySQL estricto
            // rechaza un string aquí, así que lo dejamos null como en producción.
            'empresa_id' => $empresaId,
            'numero_comprobante' => 'NC-' . uniqid(),
            'fecha' => $fecha,
            'glosa' => 'Asiento de prueba',
            'tipo_asiento' => 'traspaso',
            'origen_modulo' => 'manual',
            'estado' => 'MAYORIZADO',
        ]);
    }

    public function test_no_se_puede_crear_asiento_en_periodo_cerrado(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $this->service->cerrar($empresa->id, 2026, 3, $admin->load('rol'));

        $this->expectException(PeriodoCerradoException::class);
        $this->nuevoAsiento($empresa->id, '2026-03-15');
    }

    public function test_se_puede_crear_asiento_en_periodo_abierto(): void
    {
        $empresa = $this->crearEmpresa();
        $asiento = $this->nuevoAsiento($empresa->id, '2026-04-15');
        $this->assertDatabaseHas('asientos_contables', ['id' => $asiento->id]);
    }

    public function test_no_se_puede_modificar_asiento_de_un_periodo_cerrado(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $asiento = $this->nuevoAsiento($empresa->id, '2026-05-10');
        $this->service->cerrar($empresa->id, 2026, 5, $admin->load('rol'));

        $this->expectException(PeriodoCerradoException::class);
        $asiento->update(['glosa' => 'Editada despues del cierre']);
    }

    public function test_no_se_puede_eliminar_asiento_de_un_periodo_cerrado(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $asiento = $this->nuevoAsiento($empresa->id, '2026-05-10');
        $this->service->cerrar($empresa->id, 2026, 5, $admin->load('rol'));

        $this->expectException(PeriodoCerradoException::class);
        $asiento->delete();
    }

    public function test_no_se_puede_mover_un_asiento_hacia_un_periodo_cerrado(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $asiento = $this->nuevoAsiento($empresa->id, '2026-04-10'); // periodo abierto
        $this->service->cerrar($empresa->id, 2026, 3, $admin->load('rol')); // cierra marzo

        $this->expectException(PeriodoCerradoException::class);
        $asiento->update(['fecha' => '2026-03-20']); // intenta moverlo a marzo cerrado
    }

    public function test_reapertura_requiere_jerarquia_suficiente(): void
    {
        $empresa = $this->crearEmpresa();
        $admin = $this->crearUsuario($empresa, $this->rolAdministrador)->load('rol');
        $contador = $this->crearUsuario($empresa, $this->rolContador)->load('rol');

        $this->service->cerrar($empresa->id, 2026, 6, $admin);

        // Contador (jerarquia 50) NO puede reabrir.
        try {
            $this->service->reabrir($empresa->id, 2026, 6, $contador, 'intento');
            $this->fail('El contador no deberia poder reabrir');
        } catch (AuthorizationException $e) {
            $this->assertTrue(true);
        }

        // Admin (jerarquia 80) SI puede; tras reabrir se puede volver a escribir.
        $this->service->reabrir($empresa->id, 2026, 6, $admin, 'correccion autorizada');
        $this->assertFalse($this->service->estaCerrado($empresa->id, '2026-06-15'));

        $asiento = $this->nuevoAsiento($empresa->id, '2026-06-15');
        $this->assertDatabaseHas('asientos_contables', ['id' => $asiento->id]);
    }

    public function test_cierre_es_aislado_por_empresa(): void
    {
        [$empresaA, $adminA] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa();

        $this->service->cerrar($empresaA->id, 2026, 7, $adminA->load('rol'));

        $this->assertTrue($this->service->estaCerrado($empresaA->id, '2026-07-01'));
        $this->assertFalse($this->service->estaCerrado($empresaB->id, '2026-07-01'));

        // La empresa B puede crear asientos en julio sin problema.
        $asiento = $this->nuevoAsiento($empresaB->id, '2026-07-15');
        $this->assertDatabaseHas('asientos_contables', ['id' => $asiento->id]);
    }

    public function test_cerrar_es_idempotente(): void
    {
        [$empresa, $admin] = $this->crearEmpresaConAdmin();
        $admin->load('rol');

        $p1 = $this->service->cerrar($empresa->id, 2026, 8, $admin);
        $p2 = $this->service->cerrar($empresa->id, 2026, 8, $admin);

        $this->assertSame($p1->id, $p2->id);
        $this->assertSame(1, \App\Domains\Contabilidad\Models\PeriodoContable::query()
            ->where('empresa_id', $empresa->id)->where('anio', 2026)->where('mes', 8)->count());
    }
}
