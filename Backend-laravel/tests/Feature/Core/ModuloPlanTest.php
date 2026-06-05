<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use App\Domains\Inventario\Services\InventarioPermisoService;
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

    public function test_el_plan_acota_incluso_a_un_rol_amplio(): void
    {
        $empresa = Empresa::create(['rut' => '81.000.000-1', 'razon_social' => 'Techo SpA', 'regimen_tributario' => '14_D3']);

        // Rol Administrador (amplio: incluye compras.ver) pero plan limitado a 'clientes'.
        $user = User::create([
            'nombre'                => 'Admin Acotado',
            'email'                 => 'admin@techo.cl',
            'password'              => bcrypt('x'),
            'empresa_id'            => $empresa->id,
            'rol_id'                => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
            'module_keys'           => ['clientes'],
            'subscription_status'   => 'active',
        ]);

        // El rol concederia compras.ver, pero el plan (techo) solo habilita 'clientes'.
        $this->actingAs($user)->getJson('/api/clientes')->assertStatus(200);
        $this->actingAs($user)->getJson('/api/facturas')->assertStatus(403);
    }

    public function test_inventario_respeta_el_techo_del_plan_para_admin(): void
    {
        $empresa = Empresa::create(['rut' => '82.000.000-2', 'razon_social' => 'Inv SpA', 'regimen_tributario' => '14_D3']);
        $svc = app(InventarioPermisoService::class);

        $base = [
            'password'              => bcrypt('x'),
            'empresa_id'            => $empresa->id,
            'rol_id'                => $this->rolAdministrador->id,
            'estado_suscripcion_id' => $this->estadoSuscripcionActiva->id,
            'subscription_status'   => 'active',
        ];

        // Admin con plan que NO incluye inventario.productos -> denegado (techo manda sobre el atajo).
        $sinInv = User::create(array_merge($base, ['nombre' => 'A', 'email' => 'a@inv.cl', 'module_keys' => ['clientes']]));
        $this->assertFalse($svc->tiene($sinInv, 'inventario.productos.ver'));

        // Admin con plan que SI incluye inventario.productos -> permitido.
        $conInv = User::create(array_merge($base, ['nombre' => 'B', 'email' => 'b@inv.cl', 'module_keys' => ['inventario.productos']]));
        $this->assertTrue($svc->tiene($conInv, 'inventario.productos.ver'));

        // Admin local SIN plan (module_keys vacio) -> mantiene el atajo historico.
        $local = User::create(array_merge($base, ['nombre' => 'C', 'email' => 'c@inv.cl', 'module_keys' => []]));
        $this->assertTrue($svc->tiene($local, 'inventario.productos.ver'));
    }
}
