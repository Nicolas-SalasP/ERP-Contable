<?php

namespace App\Domains\Core\Controllers\Internal;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AdminEmpresasController
{
    /**
     * Lista todas las empresas (tenants) con agregados de sus usuarios.
     *
     * Respuesta: { "empresas": [ {id, rut, razon_social, regimen_tributario,
     *   color_primario, created_at, usuarios_count, online_count, ultimo_acceso,
     *   planes: [...] }, ... ] }
     */
    public function index(): JsonResponse
    {
        $threshold = now()->subMinutes(30);

        // Agregados por empresa calculados en una sola pasada (evita N+1).
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

        // Planes distintos (no nulos) por empresa en una sola consulta.
        $planesPorEmpresa = DB::table('usuarios')
            ->whereNotNull('plan_slug')
            ->select('empresa_id', 'plan_slug')
            ->distinct()
            ->get()
            ->groupBy('empresa_id')
            ->map(fn ($rows) => $rows->pluck('plan_slug')->values()->all());

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

    /**
     * Detalle de una empresa con el listado de sus usuarios.
     *
     * Respuesta: { "empresa": { ...campos, usuarios: [ {id, nombre, email,
     *   plan_slug, rol, estado_suscripcion, ultimo_acceso, online, bloqueado} ] } }
     * 404 JSON si no existe.
     */
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
                'u.bloqueado_hasta'
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
                ];
            })->values();

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
            ],
        ]);
    }

    /**
     * Lista PLANA de todos los usuarios del ERP (vista user-centric).
     *
     * Respuesta: { "usuarios": [ {id, nombre, email, empresa_id, empresa,
     *   plan_slug, rol, estado_suscripcion, ultimo_acceso, online, bloqueado} ] }
     *
     * Evita N+1 mediante JOINs a empresas, roles y estados_suscripcion.
     * Ordena por ultimo_acceso DESC con los nulos al final.
     */
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

    /**
     * Suspende una empresa (tenant): empresas.activa = false.
     * Respuesta: { success:true, empresa:{ id, activa:false } }. 404 si no existe.
     */
    public function suspender($id): JsonResponse
    {
        return $this->setActiva($id, false);
    }

    /**
     * Activa una empresa (tenant): empresas.activa = true.
     * Respuesta: { success:true, empresa:{ id, activa:true } }. 404 si no existe.
     */
    public function activar($id): JsonResponse
    {
        return $this->setActiva($id, true);
    }

    /**
     * Helper interno: cambia el estado activa de una empresa.
     */
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

    /**
     * Cambia el plan de TODOS los usuarios de una empresa.
     * Body: { plan_slug: string|null, module_keys: array }
     * Respuesta: { success:true, updated:<n>, plan_slug, module_keys }.
     */
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

            $updated = DB::transaction(function () use ($id, $planSlug, $moduleKeys) {
                return DB::table('usuarios')
                    ->where('empresa_id', $id)
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
        } catch (\Illuminate\Validation\ValidationException $e) {
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

    /**
     * Bloquea un usuario. Body opcional: { hasta?: 'Y-m-d H:i:s' }.
     * Sin 'hasta' => bloqueo indefinido (now()+100 años).
     * Respuesta: { success:true, usuario:{ id, bloqueado:true, bloqueado_hasta } }. 404 si no existe.
     */
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

            return response()->json([
                'success' => true,
                'usuario' => [
                    'id' => (int) $id,
                    'bloqueado' => true,
                    'bloqueado_hasta' => $hasta,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
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

    /**
     * Desbloquea un usuario: bloqueado_hasta=null y resetea contadores de bloqueo.
     * Respuesta: { success:true, usuario:{ id, bloqueado:false } }. 404 si no existe.
     */
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
