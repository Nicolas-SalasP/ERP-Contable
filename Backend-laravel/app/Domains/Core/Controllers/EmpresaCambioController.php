<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Domains\Core\Support\ModuloPermisos;

class EmpresaCambioController
{
    /** Lista las empresas del usuario autenticado, indicando cuál es la activa actualmente. */
    public function misEmpresas(Request $request)
    {
        /** @var \App\Domains\Core\Models\User $user */
        $user = $request->user();

        $empresas = DB::table('empresa_user')
            ->join('empresas', 'empresas.id', '=', 'empresa_user.empresa_id')
            ->where('empresa_user.user_id', $user->id)
            ->select(
                'empresas.id',
                'empresas.rut',
                'empresas.razon_social',
                'empresa_user.rol_id'
            )
            ->get()
            ->map(fn ($e) => [
                'id'           => $e->id,
                'rut'          => $e->rut,
                'razon_social' => $e->razon_social,
                'rol_id'       => $e->rol_id,
                'activa'       => $e->id === $user->empresa_activa_id,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $empresas]);
    }

    /** Cambia la empresa activa del usuario autenticado; requiere plan multitenant y pertenencia a la empresa destino. */
    public function cambiar(Request $request)
    {
        $request->validate([
            'empresa_id' => 'required|integer',
        ]);

        /** @var \App\Domains\Core\Models\User $user */
        $user      = $request->user();
        $empresaId = (int) $request->input('empresa_id');

        // Gate de plan: solo usuarios con módulo multitenant pueden cambiar empresa.
        if (!in_array('multitenant', $user->module_keys ?? [], true)) {
            return response()->json([
                'message' => 'Plan no permite múltiples empresas',
                'code'    => 'PLAN_NO_MULTITENANT',
            ], 403);
        }

        // Gate de pertenencia: el usuario debe tener fila en el pivote.
        $pivote = DB::table('empresa_user')
            ->where('user_id', $user->id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$pivote) {
            return response()->json([
                'message' => 'No tienes acceso a esta empresa',
                'code'    => 'EMPRESA_NO_AUTORIZADA',
            ], 403);
        }

        // Cambio atómico: actualiza empresa activa y rol efectivo en una transacción.
        DB::transaction(function () use ($user, $empresaId, $pivote) {
            $user->update([
                'empresa_activa_id' => $empresaId,
                'rol_id'            => $pivote->rol_id,
            ]);
            $user->refresh();
        });

        $user->refresh();

        $empresa = DB::table('empresas')
            ->where('id', $empresaId)
            ->select('id', 'rut', 'razon_social')
            ->first();

        $permisos = ModuloPermisos::permisosUsuario($user);

        return response()->json([
            'message' => 'Empresa activa actualizada',
            'data'    => [
                'empresa_activa_id' => $user->empresa_activa_id,
                'rol_id'            => $user->rol_id,
                'empresa'           => [
                    'id'           => $empresa->id,
                    'rut'          => $empresa->rut,
                    'razon_social' => $empresa->razon_social,
                ],
                'permisos'          => $permisos,
            ],
        ]);
    }
}
