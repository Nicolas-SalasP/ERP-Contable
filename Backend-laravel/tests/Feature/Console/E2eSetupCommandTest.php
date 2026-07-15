<?php

namespace Tests\Feature\Console;

use App\Domains\Comercial\Models\Cliente;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Tesoreria\Models\CuentaBancariaEmpresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class E2eSetupCommandTest extends TestCase
{
    use PreparaEntornoBase;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_falla_si_no_hay_ninguna_empresa(): void
    {
        $this->artisan('tenri:e2e-setup')
            ->assertExitCode(1);

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_crea_usuario_cliente_y_cuenta_bancaria_de_prueba(): void
    {
        $empresa = Empresa::create(['rut' => '76000000-0', 'razon_social' => 'Empresa E2E']);

        $this->artisan('tenri:e2e-setup')
            ->assertExitCode(0);

        $usuario = User::where('email', 'e2e_runner@tenri.cl')->first();
        $this->assertNotNull($usuario);
        $this->assertSame($empresa->id, $usuario->empresa_id);
        $this->assertTrue(Hash::check('E2ePassword_2026', $usuario->password));

        $cliente = Cliente::where('razon_social', 'Cliente E2E de Prueba')
            ->where('empresa_id', $empresa->id)
            ->first();
        $this->assertNotNull($cliente);

        $planCuenta = PlanCuenta::where('empresa_id', $empresa->id)->where('codigo', '1101')->first();
        $this->assertNotNull($planCuenta);

        // numero_cuenta usa el cast "encrypted" de Laravel (no deterministico) -- se busca por
        // "banco" (no cifrado), igual que hace el propio comando.
        $cuentaBancaria = CuentaBancariaEmpresa::where('empresa_id', $empresa->id)
            ->where('banco', 'Banco E2E')
            ->first();
        $this->assertNotNull($cuentaBancaria);
        $this->assertSame('1101', $cuentaBancaria->cuenta_contable);
        $this->assertSame('999-000-E2E', $cuentaBancaria->numero_cuenta);
    }

    public function test_es_idempotente_correr_dos_veces_no_duplica_datos(): void
    {
        Empresa::create(['rut' => '76000000-0', 'razon_social' => 'Empresa E2E']);

        $this->artisan('tenri:e2e-setup')->assertExitCode(0);
        $this->artisan('tenri:e2e-setup')->assertExitCode(0);

        $this->assertDatabaseCount('usuarios', 1);
        $this->assertSame(1, Cliente::where('razon_social', 'Cliente E2E de Prueba')->count());
        $this->assertSame(1, CuentaBancariaEmpresa::where('banco', 'Banco E2E')->count());
    }
}
