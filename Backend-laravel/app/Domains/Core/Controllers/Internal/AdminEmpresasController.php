<?php

namespace App\Domains\Core\Controllers\Internal;

use App\Domains\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminEmpresasController
{
    public function index(): JsonResponse
    {
        $threshold = now()->subMinutes(30);

        $empresas = DB::table('empresas as e')
            ->leftJoin('usuarios as u', 'u.empresa_id', '=', 'e.id')
            ->groupBy(
                'e.id',
                'e.rut',
                'e.razon_social',
                'e.regimen_tributario',
                'e.color_primario',
                'e.activa',
                'e.created_at'
            )
            ->select(
                'e.id',
                'e.rut',
                'e.razon_social',
                'e.regimen_tributario',
                'e.color_primario',
                'e.activa',
                'e.created_at',
                DB::raw('COUNT(u.id) as usuarios_count'),
                DB::raw('SUM(CASE WHEN u.ultimo_acceso >= ? THEN 1 ELSE 0 END) as online_count'),
                DB::raw('MAX(u.ultimo_acceso) as ultimo_acceso')
            )
            ->addBinding($threshold, 'select')
            ->orderBy('e.id')
            ->get();

        $planesPorEmpresa = DB::table('usuarios')
            ->whereNotNull('plan_slug')
            ->select('empresa_id', 'plan_slug')
            ->distinct()
            ->get()
            ->groupBy('empresa_id')
            ->map(fn($rows) => $rows->pluck('plan_slug')->values()->all());

        $data = $empresas->map(function ($e) use ($planesPorEmpresa) {
            return [
                'id' => (int) $e->id,
                'rut' => $e->rut,
                'razon_social' => $e->razon_social,
                'regimen_tributario' => $e->regimen_tributario,
                'color_primario' => $e->color_primario,
                'activa' => (bool) $e->activa,
                'created_at' => $e->created_at,
                'usuarios_count' => (int) $e->usuarios_count,
                'online_count' => (int) $e->online_count,
                'ultimo_acceso' => $e->ultimo_acceso,
                'planes' => $planesPorEmpresa->get($e->id, []),
            ];
        })->values();

        return response()->json(['empresas' => $data]);
    }

    public function show($id): JsonResponse
    {
        $empresa = DB::table('empresas')->where('id', $id)->first();

        if ($empresa === null) {
            return response()->json([
                'success' => false,
                'message' => 'Empresa no encontrada.',
            ], 404);
        }

        $now = now();
        $threshold = $now->copy()->subMinutes(30);

        $usuarios = DB::table('usuarios as u')
            ->leftJoin('roles as r', 'u.rol_id', '=', 'r.id')
            ->leftJoin('estados_suscripcion as es', 'u.estado_suscripcion_id', '=', 'es.id')
            ->where('u.empresa_id', $id)
            ->select(
                'u.id',
                'u.nombre',
                'u.email',
                'u.plan_slug',
                'u.rol_id',
                'r.nombre as rol_nombre',
                'u.estado_suscripcion_id',
                'es.nombre as estado_suscripcion_nombre',
                'u.ultimo_acceso',
                'u.bloqueado_hasta',
                'u.module_keys'
            )
            ->orderBy('u.id')
            ->get()
            ->map(function ($u) use ($threshold, $now) {
                $online = $u->ultimo_acceso !== null
                    && $u->ultimo_acceso >= $threshold->toDateTimeString();
                $bloqueado = $u->bloqueado_hasta !== null
                    && $u->bloqueado_hasta > $now->toDateTimeString();

                return [
                    'id' => (int) $u->id,
                    'nombre' => $u->nombre,
                    'email' => $u->email,
                    'plan_slug' => $u->plan_slug,
                    'rol' => $u->rol_nombre ?? $u->rol_id,
                    'estado_suscripcion' => $u->estado_suscripcion_nombre ?? $u->estado_suscripcion_id,
                    'ultimo_acceso' => $u->ultimo_acceso,
                    'online' => $online,
                    'bloqueado' => $bloqueado,
                    'module_keys' => json_decode($u->module_keys ?? '[]', true) ?? [],
                ];
            })->values();

        $moduleKeysEmpresa = array_values(array_unique(array_merge(...($usuarios->pluck('module_keys')->filter()->toArray() ?: [[]]))));

        return response()->json([
            'empresa' => [
                'id' => (int) $empresa->id,
                'rut' => $empresa->rut,
                'razon_social' => $empresa->razon_social,
                'direccion' => $empresa->direccion,
                'email' => $empresa->email,
                'telefono' => $empresa->telefono,
                'logo_path' => $empresa->logo_path,
                'color_primario' => $empresa->color_primario,
                'regimen_tributario' => $empresa->regimen_tributario,
                'tasa_impuesto' => $empresa->tasa_impuesto,
                'activa' => (bool) $empresa->activa,
                'created_at' => $empresa->created_at,
                'usuarios' => $usuarios,
                'module_keys' => $moduleKeysEmpresa,
            ],
        ]);
    }

    public function usuarios(): JsonResponse
    {
        $now = now();
        $threshold = $now->copy()->subMinutes(30);

        $usuarios = DB::table('usuarios as u')
            ->leftJoin('empresas as e', 'u.empresa_id', '=', 'e.id')
            ->leftJoin('roles as r', 'u.rol_id', '=', 'r.id')
            ->leftJoin('estados_suscripcion as es', 'u.estado_suscripcion_id', '=', 'es.id')
            ->select(
                'u.id',
                'u.nombre',
                'u.email',
                'u.empresa_id',
                'e.razon_social as empresa_razon_social',
                'u.plan_slug',
                'u.rol_id',
                'r.nombre as rol_nombre',
                'u.estado_suscripcion_id',
                'es.nombre as estado_suscripcion_nombre',
                'u.ultimo_acceso',
                'u.bloqueado_hasta'
            )
            ->orderByRaw('u.ultimo_acceso IS NULL')
            ->orderBy('u.ultimo_acceso', 'desc')
            ->get()
            ->map(function ($u) use ($threshold, $now) {
                $online = $u->ultimo_acceso !== null
                    && $u->ultimo_acceso >= $threshold->toDateTimeString();
                $bloqueado = $u->bloqueado_hasta !== null
                    && $u->bloqueado_hasta > $now->toDateTimeString();

                return [
                    'id' => (int) $u->id,
                    'nombre' => $u->nombre,
                    'email' => $u->email,
                    'empresa_id' => $u->empresa_id !== null ? (int) $u->empresa_id : null,
                    'empresa' => $u->empresa_razon_social,
                    'plan_slug' => $u->plan_slug,
                    'rol' => $u->rol_nombre ?? $u->rol_id,
                    'estado_suscripcion' => $u->estado_suscripcion_nombre ?? $u->estado_suscripcion_id,
                    'ultimo_acceso' => $u->ultimo_acceso,
                    'online' => $online,
                    'bloqueado' => $bloqueado,
                ];
            })->values();

        return response()->json(['usuarios' => $usuarios]);
    }

    public function suspender($id): JsonResponse
    {
        return $this->setActiva($id, false);
    }

    public function activar($id): JsonResponse
    {
        return $this->setActiva($id, true);
    }

    private function setActiva($id, bool $activa): JsonResponse
    {
        try {
            $empresa = DB::table('empresas')->where('id', $id)->first();

            if ($empresa === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada.',
                ], 404);
            }

            DB::table('empresas')->where('id', $id)->update(['activa' => $activa]);

            // Al suspender, se revocan todos los tokens activos para que el acceso cese de inmediato.
            // Se incluye tanto la empresa "hogar" (empresa_id) como la empresa "activa" (empresa_activa_id)
            // para no dejar sesiones vivas en usuarios multiempresa operando en la empresa suspendida.
            if (!$activa) {
                User::where(function ($q) use ($id) {
                    $q->where('empresa_id', $id)->orWhere('empresa_activa_id', $id);
                })->each(fn($u) => $u->tokens()->delete());
            }

            return response()->json([
                'success' => true,
                'empresa' => [
                    'id' => (int) $id,
                    'activa' => $activa,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado de la empresa.',
            ], 500);
        }
    }

    public function cambiarPlan(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'plan_slug' => 'present|nullable|string',
                'module_keys' => 'present|array',
            ]);

            $empresa = DB::table('empresas')->where('id', $id)->first();

            if ($empresa === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa no encontrada.',
                ], 404);
            }

            $planSlug = $validated['plan_slug'];
            $moduleKeys = array_values($validated['module_keys']);

            $updated = DB::transaction(function () use ($id, $planSlug, $moduleKeys): int {
                // Se actualiza tanto a los usuarios "hogar" de la empresa como a los que la tienen como empresa activa,
                // para que el plan no quede desactualizado en escenarios multiempresa.
                return DB::table('usuarios')
                    ->where(function ($q) use ($id) {
                        $q->where('empresa_id', $id)->orWhere('empresa_activa_id', $id);
                    })
                    ->update([
                        'plan_slug' => $planSlug,
                        'module_keys' => json_encode($moduleKeys),
                    ]);
            });

            return response()->json([
                'success' => true,
                'updated' => (int) $updated,
                'plan_slug' => $planSlug,
                'module_keys' => $moduleKeys,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el plan de la empresa.',
            ], 500);
        }
    }

    public function bloquearUsuario(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'hasta' => 'sometimes|nullable|date_format:Y-m-d H:i:s',
            ]);

            $usuario = DB::table('usuarios')->where('id', $id)->first();

            if ($usuario === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            $hasta = ($validated['hasta'] ?? null) !== null
                ? $validated['hasta']
                : now()->addYears(100)->toDateTimeString();

            DB::table('usuarios')->where('id', $id)->update([
                'bloqueado_hasta' => $hasta,
            ]);

            // Revocar tokens activos para que el bloqueo sea inmediato.
            $user = User::find($id);
            $user?->tokens()->delete();

            return response()->json([
                'success' => true,
                'usuario' => [
                    'id' => (int) $id,
                    'bloqueado' => true,
                    'bloqueado_hasta' => $hasta,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al bloquear el usuario.',
            ], 500);
        }
    }

    public function desbloquearUsuario($id): JsonResponse
    {
        try {
            $usuario = DB::table('usuarios')->where('id', $id)->first();

            if ($usuario === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.',
                ], 404);
            }

            DB::table('usuarios')->where('id', $id)->update([
                'bloqueado_hasta' => null,
                'intentos_fallidos' => 0,
                'nivel_bloqueo' => 0,
            ]);

            return response()->json([
                'success' => true,
                'usuario' => [
                    'id' => (int) $id,
                    'bloqueado' => false,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desbloquear el usuario.',
            ], 500);
        }
    }
}