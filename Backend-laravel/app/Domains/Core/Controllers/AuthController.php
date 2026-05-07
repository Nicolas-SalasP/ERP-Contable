<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Throwable;

class AuthController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Cargar usuario con su rol
            |--------------------------------------------------------------------------
            |
            | Los permisos reales viven en roles.permisos y se asignan desde
            | GestionRoles.jsx. El AuthController solo los lee y los entrega
            | al frontend en runtime.
            |
            */
            $user = User::with('rol')
                ->where('email', $request->email)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Credenciales incorrectas',
                ], 401);
            }

            $user->update([
                'ultimo_acceso' => now(),
            ]);

            $permisos = $this->permisosDelUsuario($user);

            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id' => $user->rol_id,
                    'rol' => $user->rol?->nombre,
                    'permisos' => $permisos,
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Error Interno de Laravel',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    public function me(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Usuario autenticado
        |--------------------------------------------------------------------------
        |
        | Devuelve datos del usuario y permisos reales. Sirve para refrescar
        | permisos después de cambios desde Roles y Permisos.
        |
        */
        $user = $request->user()->load(['empresa', 'rol']);

        return response()->json([
            'id' => $user->id,
            'nombre' => $user->nombre,
            'email' => $user->email,
            'empresa_id' => $user->empresa_id,
            'rol_id' => $user->rol_id,
            'rol' => $user->rol?->nombre,
            'permisos' => $this->permisosDelUsuario($user),
        ]);
    }

    private function permisosDelUsuario(User $user): array
    {
        if (!$user->relationLoaded('rol')) {
            $user->load('rol');
        }

        $nombreRol = strtolower((string) ($user->rol->nombre ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Permisos guardados en el rol
        |--------------------------------------------------------------------------
        |
        | Estos permisos vienen desde roles.permisos. Pueden haber sido
        | asignados visualmente desde GestionRoles.jsx.
        |
        */
        $permisosRol = $this->normalizarPermisos($user->rol->permisos ?? []);

        /*
        |--------------------------------------------------------------------------
        | Administrador
        |--------------------------------------------------------------------------
        |
        | El administrador mantiene permisos runtime globales, pero ahora
        | también mezcla los permisos reales guardados en roles.permisos.
        |
        | Esto evita que al agregar nuevas fases del ERP se pierdan permisos
        | en el login por olvidar agregarlos en la lista fija.
        |
        */
        if ($nombreRol === 'administrador') {
            return $this->unirPermisos(
                $this->permisosAdministrador(),
                $permisosRol
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Roles normales
        |--------------------------------------------------------------------------
        |
        | Contador, Auditor, Ventas u otros roles reciben solo los permisos
        | asignados desde roles.permisos.
        |
        */
        return $permisosRol;
    }

    private function normalizarPermisos(mixed $permisos): array
    {
        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }

        if (!is_array($permisos)) {
            return [];
        }

        return array_values(array_unique(array_filter($permisos)));
    }

    private function unirPermisos(array ...$grupos): array
    {
        return array_values(array_unique(array_merge(...$grupos)));
    }

    private function permisosAdministrador(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Permisos runtime del administrador
        |--------------------------------------------------------------------------
        |
        | Lista base de superusuario. No escribe en BD.
        | Se mezcla con roles.permisos para no perder permisos nuevos.
        |
        */
        return [
            /*
            |--------------------------------------------------------------------------
            | Ventas / clientes
            |--------------------------------------------------------------------------
            */
            'ventas.ver',
            'ventas.crear',
            'ventas.anular',
            'clientes.ver',
            'clientes.crear',
            'clientes.gestionar',

            /*
            |--------------------------------------------------------------------------
            | Compras / proveedores
            |--------------------------------------------------------------------------
            */
            'compras.ver',
            'compras.crear',
            'proveedores.ver',
            'proveedores.crear',
            'proveedores.gestionar',

            /*
            |--------------------------------------------------------------------------
            | Tesorería
            |--------------------------------------------------------------------------
            */
            'tesoreria.ver',
            'tesoreria.crear',
            'bancos.gestionar',
            'conciliacion.ejecutar',

            /*
            |--------------------------------------------------------------------------
            | Contabilidad
            |--------------------------------------------------------------------------
            */
            'contabilidad.ver',
            'contabilidad.crear',
            'asientos.crear',
            'plan_cuentas.editar',

            /*
            |--------------------------------------------------------------------------
            | Activos
            |--------------------------------------------------------------------------
            */
            'activos.ver',
            'activos.crear',
            'activos.gestionar',
            'proyectos.crear',

            /*
            |--------------------------------------------------------------------------
            | Tributario
            |--------------------------------------------------------------------------
            */
            'tributario.ver',
            'tributario.crear',
            'f29.ver',
            'f29.ejecutar',
            'renta.ver',

            /*
            |--------------------------------------------------------------------------
            | Administración
            |--------------------------------------------------------------------------
            */
            'usuarios.ver',
            'usuarios.gestionar',
            'roles.gestionar',
            'empresa.editar',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 1
            |--------------------------------------------------------------------------
            */
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 2
            |--------------------------------------------------------------------------
            */
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 3
            |--------------------------------------------------------------------------
            */
            'inventario.valorizacion.ver',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 4
            |--------------------------------------------------------------------------
            */
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 5
            |--------------------------------------------------------------------------
            */
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 6
            |--------------------------------------------------------------------------
            */
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',

            /*
            |--------------------------------------------------------------------------
            | Inventario - Fase 7
            |--------------------------------------------------------------------------
            */
            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }
}
