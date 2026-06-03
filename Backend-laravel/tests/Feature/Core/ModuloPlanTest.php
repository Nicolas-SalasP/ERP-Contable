<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class ModuloPlanTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_el_plan_gobierna_el_acceso_via_module_keys(): void
    {
        $empresa = Empresa::create(['rut' => '80.000.000-0', 'razon_social' => 'Plan SpA', 'regimen_tributario' => '14_D3']);

        // Rol de baja jerarquia y sin permisos propios: el acceso depende solo del plan.
        $user = User::create([
            'nombre'                => 'Basico',
            'email'                 => 'basico@plan.cl',
            'password'              => bcrypt('x'),
            'empresa_id'            => $empresa->id,
            'rol_id'                => $this->rolUsuarioBasico->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
            'module_keys'           => ['clientes'],
            'subscription_status'   => 'active',
        ]);

        // 'clientes' otorga clientes.ver / ventas.ver -> el endpoint de clientes pasa.
        $this->actingAs($user)->getJson('/api/clientes')->assertStatus(200);

        // 'clientes' NO otorga compras.ver -> el endpoint de facturas queda bloqueado.
        $this->actingAs($user)->getJson('/api/facturas')->assertStatus(403);
    }
}
