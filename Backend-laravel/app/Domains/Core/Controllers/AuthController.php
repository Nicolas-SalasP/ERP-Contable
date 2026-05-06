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
            | Es importante cargar la relacion rol porque los permisos reales
            | ahora viven en roles.permisos y se asignan desde el gestor visual
            | de roles, no desde Inventario ni desde seeders de Inventario.
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

            /*
            |--------------------------------------------------------------------------
            | Permisos del usuario
            |--------------------------------------------------------------------------
            |
            | Administrador recibe permisos runtime de superusuario.
            | Otros roles reciben lo que este guardado en roles.permisos.
            |
            | Esto NO crea permisos.
            | Esto NO asigna permisos.
            | Esto solo lee permisos existentes.
            |
            */
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
        | Devuelve los datos del usuario actual y sus permisos reales.
        | El frontend puede usar este endpoint para refrescar permisos despues
        | de cambios en el gestor de roles.
        |
        */
        $user = $request->user()->load(['empresa', 'rol']);

        $userData = $user->toArray();
        $userData['permisos'] = $this->permisosDelUsuario($user);

        return response()->json($userData);
    }

    private function permisosDelUsuario(User $user): array
    {
        if (!$user->relationLoaded('rol')) {
            $user->load('rol');
        }

        $nombreRol = strtolower((string) ($user->rol->nombre ?? ''));

        /*
        |--------------------------------------------------------------------------
        | Administrador
        |--------------------------------------------------------------------------
        |
        | El administrador mantiene permisos de superusuario en runtime.
        | No se escriben estos permisos en la base de datos.
        |
        | Esto evita que Inventario tenga que asignar permisos por migracion
        | o seeder, pero permite que admin pueda operar todo el sistema.
        |
        */
        if ($nombreRol === 'administrador') {
            return $this->permisosAdministrador();
        }

        /*
        |--------------------------------------------------------------------------
        | Roles normales
        |--------------------------------------------------------------------------
        |
        | Contador, Auditor, Ventas u otros roles reciben solo los permisos
        | guardados en roles.permisos.
        |
        | Esos permisos se asignan desde el gestor visual de roles.
        |
        */
        $permisos = $user->rol->permisos ?? [];

        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?: [];
        }

        if (!is_array($permisos)) {
            return [];
        }

        return array_values(array_unique($permisos));
    }

    private function permisosAdministrador(): array
    {
        /*
        |--------------------------------------------------------------------------
        | Permisos runtime del administrador
        |--------------------------------------------------------------------------
        |
        | Lista usada solo al autenticar o consultar /me.
        | No modifica roles.permisos.
        |
        | Incluye permisos globales existentes y permisos de Inventario.
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
            | Tesoreria
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
            | Administracion
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
        ];
    }
}