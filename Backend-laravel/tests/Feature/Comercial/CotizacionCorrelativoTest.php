<?php

namespace Tests\Feature\Comercial;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Comercial\Models\Cotizacion;
use App\Domains\Comercial\Models\EstadoCotizacion;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Regresion: numero_cotizacion se generaba desde el id autoincremental GLOBAL de la
 * tabla cotizaciones (compartido entre todas las empresas), asi que el correlativo de
 * una empresa saltaba cada vez que otra empresa creaba una cotizacion en el medio
 * (ej. de COT-000003 a COT-000010). Ahora usa ContadorEmpresaService (correlativo
 * atomico por empresa, mismo patron que OrdenCompraService).
 */
class CotizacionCorrelativoTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    private function crearEmpresaConAdminYCliente(string $rutEmpresa, string $rutCliente): array
    {
        $empresa = Empresa::create(['rut' => $rutEmpresa, 'razon_social' => "Empresa {$rutEmpresa}"]);
        $usuario = User::create([
            'nombre' => 'Vendedor',
            'email' => "v-{$rutEmpresa}@test.cl",
            'password' => bcrypt('123'),
            'empresa_id' => $empresa->id,
            'rol_id' => $this->rolSuperAdmin->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
        ]);
        $cliente = Cliente::create([
            'empresa_id' => $empresa->id,
            'rut' => $rutCliente,
            'razon_social' => "Cliente {$rutCliente}",
            'estado' => 'ACTIVO',
        ]);

        return [$empresa, $usuario, $cliente];
    }

    private function crearCotizacionViaApi(User $usuario, Cliente $cliente): Cotizacion
    {
        $response = $this->actingAs($usuario)->postJson('/api/cotizaciones', [
            'cliente_id' => $cliente->id,
            'fecha_emision' => now()->format('Y-m-d'),
            'subtotal' => 10000,
            'detalles' => [
                ['producto_nombre' => 'Item', 'cantidad' => 1, 'precio_unitario' => 10000],
            ],
        ]);
        $response->assertStatus(201);

        return Cotizacion::findOrFail($response->json('data.id'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
        EstadoCotizacion::firstOrCreate(['id' => 1], ['nombre' => 'Borrador']);
    }

    public function test_correlativo_no_salta_cuando_otra_empresa_crea_cotizaciones_en_el_medio(): void
    {
        [$empresaA, $usuarioA, $clienteA] = $this->crearEmpresaConAdminYCliente('11.111.111-1', '22.222.222-2');
        [$empresaB, $usuarioB, $clienteB] = $this->crearEmpresaConAdminYCliente('33.333.333-3', '44.444.444-4');

        $cot1A = $this->crearCotizacionViaApi($usuarioA, $clienteA);
        $this->assertSame('COT-000001', $cot1A->numero_cotizacion);

        // Empresa B crea varias cotizaciones "en el medio" -- antes del fix, esto
        // hacia saltar el correlativo interno de A (via el id global de la tabla).
        $this->crearCotizacionViaApi($usuarioB, $clienteB);
        $this->crearCotizacionViaApi($usuarioB, $clienteB);
        $this->crearCotizacionViaApi($usuarioB, $clienteB);

        $cot2A = $this->crearCotizacionViaApi($usuarioA, $clienteA);
        $this->assertSame('COT-000002', $cot2A->numero_cotizacion);
    }

    public function test_dos_empresas_pueden_tener_ambas_una_cotizacion_cot_000001(): void
    {
        [$empresaA, $usuarioA, $clienteA] = $this->crearEmpresaConAdminYCliente('55.555.555-5', '66.666.666-6');
        [$empresaB, $usuarioB, $clienteB] = $this->crearEmpresaConAdminYCliente('77.777.777-7', '88.888.888-8');

        $cotA = $this->crearCotizacionViaApi($usuarioA, $clienteA);
        $cotB = $this->crearCotizacionViaApi($usuarioB, $clienteB);

        $this->assertSame('COT-000001', $cotA->numero_cotizacion);
        $this->assertSame('COT-000001', $cotB->numero_cotizacion);
    }
}
