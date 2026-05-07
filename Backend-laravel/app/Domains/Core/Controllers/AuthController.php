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
                'email'    => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Credenciales incorrectas'], 401);
            }
            $user->update([
                'ultimo_acceso' => now()
            ]);

            $permisos = [];
            
            if ($user->rol_id === 1) {
                $permisos = [
                    'ventas.ver', 'ventas.crear', 'clientes.ver', 'clientes.crear',
                    'compras.ver', 'compras.crear', 'proveedores.ver', 'proveedores.crear',
                    'tesoreria.ver', 'tesoreria.crear', 'contabilidad.ver', 'contabilidad.crear',
                    'activos.ver', 'activos.crear', 'tributario.ver', 'tributario.crear',
                    'usuarios.ver', 'usuarios.gestionar'
                ];
            }

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

        $permisosRol = $this->normalizarPermisos($user->rol->permisos ?? []);

        if ($nombreRol === 'administrador') {
            return $this->unirPermisos(
                $this->permisosAdministrador(),
                $permisosRol
            );
        }

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
        return [
            'ventas.ver',
            'ventas.crear',
            'ventas.anular',
            'clientes.ver',
            'clientes.crear',
            'clientes.gestionar',
            'compras.ver',
            'compras.crear',
            'proveedores.ver',
            'proveedores.crear',
            'proveedores.gestionar',
            'tesoreria.ver',
            'tesoreria.crear',
            'bancos.gestionar',
            'conciliacion.ejecutar',
            'contabilidad.ver',
            'contabilidad.crear',
            'asientos.crear',
            'plan_cuentas.editar',
            'activos.ver',
            'activos.crear',
            'activos.gestionar',
            'proyectos.crear',
            'tributario.ver',
            'tributario.crear',
            'f29.ver',
            'f29.ejecutar',
            'renta.ver',
            'usuarios.ver',
            'usuarios.gestionar',
            'roles.gestionar',
            'empresa.editar',
            'inventario.productos.ver',
            'inventario.productos.crear',
            'inventario.productos.editar',
            'inventario.bodegas.ver',
            'inventario.bodegas.crear',
            'inventario.movimientos.ver',
            'inventario.movimientos.entrada',
            'inventario.movimientos.salida',
            'inventario.movimientos.traspaso',
            'inventario.movimientos.ajuste',
            'inventario.kardex.ver',
            'inventario.valorizacion.ver',
            'inventario.ajustes_criticos.ver',
            'inventario.ajustes_criticos.crear',
            'inventario.lotes.ver',
            'inventario.lotes.crear',
            'inventario.lotes.editar',
            'inventario.reservas.ver',
            'inventario.reservas.crear',
            'inventario.reservas.cancelar',
            'inventario.reservas.liberar',
            'inventario.reservas.consumir',
            'inventario.disponibilidad.ver',
            'inventario.tomas_fisicas.ver',
            'inventario.tomas_fisicas.crear',
            'inventario.tomas_fisicas.contar',
            'inventario.tomas_fisicas.cerrar',
            'inventario.tomas_fisicas.ajustar',
            'inventario.tomas_fisicas.cancelar',
        ];
    }
}
