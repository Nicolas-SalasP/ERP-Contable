<?php

namespace Tests\Feature\Core;

use App\Domains\Core\Scopes\EmpresaScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guardian anti-regresion del aislamiento multitenant (P1).
 *
 * Escanea todos los modelos de los dominios y exige que cualquiera con columna
 * empresa_id tenga el EmpresaScope. Si alguien agrega un modelo tenant nuevo sin
 * el candado, este test falla y lo nombra.
 */
class EmpresaScopeCoberturaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Modelos con empresa_id que se excluyen a proposito (ver auditoria):
     *  - User/Empresa/Rol: infraestructura de auth/tenant (recursion en login,
     *    la empresa es la raiz del tenant, roles se resuelven cerca del auth).
     *  - CM config/ejecucion: configuracion de sistema creada por EmpresaObserver
     *    en contexto cross-empresa y aislada por filtro explicito en el service.
     */
    private const EXCLUSIONES = [
        \App\Domains\Core\Models\User::class,
        \App\Domains\Core\Models\Empresa::class,
        \App\Domains\Core\Models\Rol::class,
        \App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa::class,
        \App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta::class,
        \App\Domains\CorreccionMonetaria\Models\CmEjecucion::class,
    ];

    public function test_todo_modelo_tenant_tiene_empresa_scope(): void
    {
        $files = array_merge(
            glob(app_path('Domains/*/Models/*.php')) ?: [],
            glob(app_path('Domains/*/Models/*/*.php')) ?: [],
        );

        $sinScope = [];
        $revisados = 0;

        foreach ($files as $file) {
            $src = file_get_contents($file);

            // Solo modelos tenant: declaran 'empresa_id' (mismo criterio del worklist).
            if (!str_contains($src, "'empresa_id'")) {
                continue;
            }

            if (!preg_match('/namespace\s+([^;]+);/', $src, $ns)) {
                continue;
            }
            if (!preg_match('/class\s+(\w+)/', $src, $cl)) {
                continue;
            }

            $fqcn = trim($ns[1]) . '\\' . $cl[1];

            if (in_array($fqcn, self::EXCLUSIONES, true) || !class_exists($fqcn)) {
                continue;
            }

            $revisados++;
            $model = new $fqcn();

            if (!array_key_exists(EmpresaScope::class, $model->getGlobalScopes())) {
                $sinScope[] = $fqcn;
            }
        }

        $this->assertGreaterThan(30, $revisados, 'No se detectaron suficientes modelos tenant; revisa el glob.');
        $this->assertSame(
            [],
            $sinScope,
            'Modelos tenant SIN EmpresaScope (agrega HasEmpresaScope o documenta la exclusion): '
                . implode(', ', $sinScope)
        );
    }
}
