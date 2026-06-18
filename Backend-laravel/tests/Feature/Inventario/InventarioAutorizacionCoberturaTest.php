<?php

namespace Tests\Feature\Inventario;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\PreparaInventarioTrait;
use Tests\TestCase;

/**
 * Guardian anti-regresion de autorizacion del modulo Inventario (S-5).
 *
 * Las rutas /api/inventario/* NO declaran middleware 'permiso:' — por diseno,
 * la autorizacion granular vive en la capa de servicio (InventarioPermisoService::
 * exigir/exigirAlguno), que se invoca como PRIMERA linea de cada metodo de accion
 * (antes de cualquier lookup de recurso). Esto permite permisos por tipo de
 * operacion (ej. movimientos.entrada vs movimientos.salida) que un middleware
 * grueso no podria expresar.
 *
 * El riesgo de ese patron es que un endpoint nuevo quede sin el candado si alguien
 * olvida el exigir() en el servicio: como no hay 'permiso:' en la ruta, no habria
 * red de seguridad. Este guardian cierra ese hueco: recorre TODAS las rutas GET de
 * inventario y exige 403 para un usuario autenticado sin permisos. Si un endpoint
 * nuevo responde 200, el test falla y lo nombra.
 *
 * Cubre GET (lectura, sin efectos colaterales y donde vive el riesgo de fuga de
 * datos). La autorizacion se valida antes que la existencia del recurso, por eso
 * los {parametros} se sustituyen con un valor dummy y aun asi se espera 403.
 */
class InventarioAutorizacionCoberturaTest extends TestCase
{
    use RefreshDatabase;
    use PreparaInventarioTrait;

    /**
     * Sustituye los segmentos {param} de un URI por un valor dummy. El tipo de
     * reporte usa un valor valido del enum para no chocar con validacion previa.
     */
    private function rellenarParametros(string $uri): string
    {
        return preg_replace_callback('/\{(\w+)\??\}/', function ($m) {
            return $m[1] === 'tipo' ? 'stock' : '1';
        }, $uri);
    }

    public function test_toda_ruta_get_de_inventario_exige_permiso(): void
    {
        $this->prepararUsuariosInventarioDemo();

        // Usuario autenticado pero SIN ningun permiso de inventario (rol no-admin).
        [, $usuario] = $this->usuarioAuditorConPermisos([]);

        $rutasGet = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/inventario/'))
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => $r->uri())
            ->unique()
            ->values();

        $this->assertGreaterThan(
            30,
            $rutasGet->count(),
            'No se detectaron suficientes rutas GET de inventario; revisa el filtro.'
        );

        $sinProteccion = [];

        foreach ($rutasGet as $uri) {
            $path = '/' . $this->rellenarParametros($uri);

            $status = $this->actingAs($usuario)
                ->getJson($path)
                ->getStatusCode();

            // 403 = autorizacion correcta. Cualquier 2xx es fuga: el usuario sin
            // permiso accedio al recurso. Se registra para nombrarlo en el fallo.
            if ($status < 400) {
                $sinProteccion[] = "{$uri} -> {$status}";
            }
        }

        $this->assertSame(
            [],
            $sinProteccion,
            'Rutas GET de inventario SIN autorizacion (agrega exigir/exigirAlguno en el '
                . 'servicio o permiso: en la ruta): ' . implode(', ', $sinProteccion)
        );
    }

    /**
     * Control positivo: evita que el guardian sea vacuo. Si una capa previa
     * (suscripcion, auth) bloqueara TODO con 4xx, el test anterior pasaria sin
     * probar autorizacion. Aqui un usuario CON el permiso correcto obtiene un
     * 200 en una ruta de lectura, confirmando que los 403 del otro test provienen
     * de la autorizacion granular y no de un bloqueo aguas arriba.
     */
    public function test_usuario_con_permiso_si_accede_a_lectura_de_inventario(): void
    {
        $this->prepararUsuariosInventarioDemo();

        [, $usuario] = $this->usuarioAuditorConPermisos(['inventario.dashboard.ver']);

        $this->actingAs($usuario)
            ->getJson('/api/inventario/dashboard')
            ->assertOk();
    }
}
