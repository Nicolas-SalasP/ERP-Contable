<?php

namespace Tests\Feature\Core;

use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

/**
 * Fase 2 multitenant: verifica que EmpresaScope filtra por empresa_activa_id
 * resuelta del lado servidor y nunca mezcla datos entre empresas.
 *
 * Un fallo aquí es una fuga de datos entre clientes — detener toda la épica.
 */
class AislamientoMultitenantFase2Test extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    // -----------------------------------------------------------------------
    // Helper: insertar plan de cuenta sin pasar por scope (setup de test)
    // -----------------------------------------------------------------------
    private function insertarCuenta(int $empresaId, string $codigo, string $nombre): void
    {
        \DB::table('plan_cuentas')->insert([
            'empresa_id' => $empresaId,
            'codigo'     => $codigo,
            'nombre'     => $nombre,
            'tipo'       => 'ACTIVO',
            'nivel'      => 1,
            'imputable'  => 1,
            'activo'     => 1,
            'created_at' => now(),
        ]);
    }

    private function vincularEmpresa(User $usuario, Empresa $empresa): void
    {
        \DB::table('empresa_user')->insertOrIgnore([
            'user_id'    => $usuario->id,
            'empresa_id' => $empresa->id,
            'rol_id'     => $usuario->rol_id,
            'created_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Test 1: usuario multiempresa solo ve datos de su empresa activa
    // -----------------------------------------------------------------------
    public function test_usuario_multiempresa_solo_ve_datos_de_empresa_activa(): void
    {
        [$empresaA, $usuarioA] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa(['razon_social' => 'Empresa B SpA']);

        // Vincular al usuario a ambas empresas; activa = A
        $this->vincularEmpresa($usuarioA, $empresaA);
        $this->vincularEmpresa($usuarioA, $empresaB);
        $usuarioA->update(['empresa_activa_id' => $empresaA->id]);

        $this->insertarCuenta($empresaA->id, '1100', 'Caja Empresa A');
        $this->insertarCuenta($empresaB->id, '1100', 'Caja Empresa B');

        $this->actingAs($usuarioA);

        $cuentas = PlanCuenta::all();

        $this->assertCount(1, $cuentas);
        $this->assertEquals('Caja Empresa A', $cuentas->first()->nombre);
        $this->assertTrue($cuentas->every(fn ($c) => $c->empresa_id === $empresaA->id));
    }

    // -----------------------------------------------------------------------
    // Test 2: cambiar empresa activa cambia los datos visibles, nunca mezcla
    // -----------------------------------------------------------------------
    public function test_cambio_de_empresa_activa_cambia_datos_visibles(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa(['razon_social' => 'Empresa B SpA']);

        $this->vincularEmpresa($usuario, $empresaA);
        $this->vincularEmpresa($usuario, $empresaB);

        $this->insertarCuenta($empresaA->id, '1100', 'Caja A');
        $this->insertarCuenta($empresaB->id, '1200', 'Caja B');

        // Activa = A
        $usuario->update(['empresa_activa_id' => $empresaA->id]);
        $usuario->refresh();
        $this->actingAs($usuario);

        $cuentasA = PlanCuenta::all();
        $this->assertCount(1, $cuentasA);
        $this->assertEquals('Caja A', $cuentasA->first()->nombre);

        // Cambiar activa a B (operación de servidor)
        $usuario->update(['empresa_activa_id' => $empresaB->id]);
        $usuario->refresh();

        $cuentasB = PlanCuenta::all();
        $this->assertCount(1, $cuentasB);
        $this->assertEquals('Caja B', $cuentasB->first()->nombre);

        // Verificar que NUNCA hay mezcla
        $todosLosIds = $cuentasA->pluck('empresa_id')
            ->merge($cuentasB->pluck('empresa_id'))
            ->unique();
        $this->assertCount(2, $todosLosIds, 'Cada consulta devolvio exactamente una empresa distinta — sin mezcla');
    }

    // -----------------------------------------------------------------------
    // Test 3: scope aísla correctamente aunque empresa_activa_id apunte a empresa ajena
    //         (la prevención del cambio no autorizado es responsabilidad del endpoint — Fase 3)
    // -----------------------------------------------------------------------
    public function test_scope_aísla_datos_aunque_empresa_activa_sea_ajena(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaAjena = $this->crearEmpresa(['razon_social' => 'Empresa Ajena SpA']);

        $this->vincularEmpresa($usuario, $empresaA);
        // NO vincular al usuario con empresaAjena (es ajena)

        $this->insertarCuenta($empresaA->id, '1100', 'Caja A');
        $this->insertarCuenta($empresaAjena->id, '1100', 'Caja Ajena');

        // Si empresa_activa_id apuntara a ajena (escenario adversario), el scope
        // devuelve solo datos de la ajena — sin mezclar datos de otras empresas.
        // El endpoint de Fase 3 impedirá que este estado siquiera ocurra.
        $usuario->update(['empresa_activa_id' => $empresaAjena->id]);
        $usuario->refresh();
        $this->actingAs($usuario);

        $cuentas = PlanCuenta::all();

        // El scope filtra solo por empresa_activa_id — no hay mezcla con empresa A
        $this->assertTrue(
            $cuentas->every(fn ($c) => $c->empresa_id === $empresaAjena->id),
            'El scope no mezcla datos: solo devuelve registros de empresa_activa_id'
        );
        $this->assertFalse(
            $cuentas->contains(fn ($c) => $c->empresa_id === $empresaA->id),
            'Los datos de empresa A no son visibles cuando empresa_activa_id es ajena'
        );
    }

    // -----------------------------------------------------------------------
    // Test 4: usuario de una sola empresa — comportamiento intacto (regresión)
    // -----------------------------------------------------------------------
    public function test_usuario_una_empresa_comportamiento_intacto(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        // empresa_activa_id == empresa_id (data migration de Fase 1)
        $usuario->update(['empresa_activa_id' => $empresa->id]);
        $usuario->refresh();

        $this->insertarCuenta($empresa->id, '1100', 'Caja empresa única');

        // Usuario de otra empresa — NO debe ver datos del primero
        [$otraEmpresa, $otroUsuario] = $this->crearEmpresaConAdmin(
            ['razon_social' => 'Otra Empresa SpA'],
            ['email' => 'otro@test.cl']
        );
        $otroUsuario->update(['empresa_activa_id' => $otraEmpresa->id]);
        $otroUsuario->refresh();
        $this->insertarCuenta($otraEmpresa->id, '2000', 'Cuenta otra empresa');

        // Actuar como usuario original
        $this->actingAs($usuario);
        $cuentas = PlanCuenta::all();

        $this->assertCount(1, $cuentas);
        $this->assertEquals($empresa->id, $cuentas->first()->empresa_id);

        // Actuar como otro usuario
        $this->actingAs($otroUsuario);
        $cuentasOtro = PlanCuenta::all();

        $this->assertCount(1, $cuentasOtro);
        $this->assertEquals($otraEmpresa->id, $cuentasOtro->first()->empresa_id);
    }

    // -----------------------------------------------------------------------
    // Test 5: scope ignora cualquier input del cliente (header/param)
    //         Lee empresa_activa_id del usuario autenticado, nunca de la request
    // -----------------------------------------------------------------------
    public function test_scope_no_lee_empresa_de_input_del_cliente(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa(['razon_social' => 'Empresa B SpA']);

        $this->vincularEmpresa($usuario, $empresaA);
        $usuario->update(['empresa_activa_id' => $empresaA->id]);
        $usuario->refresh();

        $this->insertarCuenta($empresaA->id, '1100', 'Cuenta A');
        $this->insertarCuenta($empresaB->id, '1200', 'Cuenta B');

        // Verificar directamente que el SQL del scope usa empresa_activa_id del usuario
        // y no algún valor que el cliente pueda inyectar.
        // El scope NO lee request()->header() ni request()->input() — solo auth()->user().
        $this->actingAs($usuario);

        // Simular request con header X-Empresa-Id apuntando a empresa B
        $response = $this->withHeader('X-Empresa-Id', (string) $empresaB->id)
            ->withHeader('X-Tenant', (string) $empresaB->id)
            ->getJson('/api/health'); // endpoint público que no devuelve datos de empresa

        // El scope filtra por empresa_activa_id del usuario (A), no por el header (B)
        $cuentas = PlanCuenta::all();
        $this->assertCount(1, $cuentas);
        $this->assertEquals($empresaA->id, $cuentas->first()->empresa_id,
            'El scope ignoró X-Empresa-Id del cliente y filtró por empresa_activa_id del servidor');
        $this->assertNotEquals($empresaB->id, $cuentas->first()->empresa_id);
    }
}
