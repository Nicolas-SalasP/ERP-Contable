<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEntornoBase;
use Tests\TestCase;

class MultitenantEmpresaUserTest extends TestCase
{
    use RefreshDatabase, PreparaEntornoBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararEntornoBase();
    }

    public function test_usuario_existente_conserva_su_empresa_tras_migracion(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        // Simular la migración de datos: insertar la fila pivote manualmente
        // (en producción lo hace la migración; en tests se hace explícito).
        \DB::table('empresa_user')->insertOrIgnore([
            'user_id'    => $usuario->id,
            'empresa_id' => $empresa->id,
            'rol_id'     => $usuario->rol_id,
            'created_at' => now(),
        ]);
        $usuario->update(['empresa_activa_id' => $empresa->id]);
        $usuario->refresh();

        $this->assertEquals($empresa->id, $usuario->empresa_activa_id);
        $this->assertEquals($empresa->id, $usuario->empresa_id);
        $this->assertTrue($usuario->empresas()->where('empresas.id', $empresa->id)->exists());
    }

    public function test_usuario_puede_pertenecer_a_varias_empresas(): void
    {
        [$empresaA, $usuario] = $this->crearEmpresaConAdmin();
        $empresaB = $this->crearEmpresa(['razon_social' => 'Empresa B SpA']);

        \DB::table('empresa_user')->insertOrIgnore([
            'user_id'    => $usuario->id,
            'empresa_id' => $empresaA->id,
            'rol_id'     => $usuario->rol_id,
            'created_at' => now(),
        ]);
        \DB::table('empresa_user')->insertOrIgnore([
            'user_id'    => $usuario->id,
            'empresa_id' => $empresaB->id,
            'rol_id'     => $usuario->rol_id,
            'created_at' => now(),
        ]);

        $this->assertCount(2, $usuario->empresas()->get());
        $this->assertTrue($usuario->empresas()->where('empresas.id', $empresaA->id)->exists());
        $this->assertTrue($usuario->empresas()->where('empresas.id', $empresaB->id)->exists());
    }

    public function test_empresa_activa_por_defecto_es_la_original(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $usuario->update(['empresa_activa_id' => $empresa->id]);
        $usuario->refresh();

        $this->assertEquals($usuario->empresa_id, $usuario->empresa_activa_id);
        $this->assertEquals($empresa->id, $usuario->empresaActiva?->id);
    }

    public function test_pivote_empresa_user_no_permite_duplicados(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();

        \DB::table('empresa_user')->insertOrIgnore([
            'user_id' => $usuario->id, 'empresa_id' => $empresa->id,
            'rol_id'  => $usuario->rol_id, 'created_at' => now(),
        ]);

        // insertOrIgnore debe ignorar el duplicado sin excepción
        \DB::table('empresa_user')->insertOrIgnore([
            'user_id' => $usuario->id, 'empresa_id' => $empresa->id,
            'rol_id'  => $usuario->rol_id, 'created_at' => now(),
        ]);

        $count = \DB::table('empresa_user')
            ->where('user_id', $usuario->id)
            ->where('empresa_id', $empresa->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_eliminar_usuario_elimina_sus_filas_del_pivote(): void
    {
        [$empresa, $usuario] = $this->crearEmpresaConAdmin();
        $userId = $usuario->id;

        \DB::table('empresa_user')->insert([
            'user_id' => $userId, 'empresa_id' => $empresa->id,
            'rol_id'  => $usuario->rol_id, 'created_at' => now(),
        ]);

        $this->assertDatabaseHas('empresa_user', ['user_id' => $userId]);

        $usuario->delete();

        $this->assertDatabaseMissing('empresa_user', ['user_id' => $userId]);
    }
}
