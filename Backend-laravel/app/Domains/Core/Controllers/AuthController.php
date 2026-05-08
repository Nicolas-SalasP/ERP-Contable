<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Models\User;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::with(['rol', 'estadoSuscripcion'])->where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            // Validar contra el nombre del estado (no contra id hardcodeado).
            // Razon: el id no es estable entre entornos / seeders y puede cambiar.
            if (!$user->estadoSuscripcion || $user->estadoSuscripcion->nombre !== 'Activa') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cuenta inactiva o suspendida.'
                ], 403);
            }

            $user->update(['ultimo_acceso' => now()]);

            $permisos = $user->rol->permisos ?? [];

            if ($user->rol && $user->rol->jerarquia >= 100) {
                $permisos = [
                    'ventas.ver',
                    'ventas.crear',
                    'clientes.ver',
                    'clientes.crear',
                    'compras.ver',
                    'compras.crear',
                    'proveedores.ver',
                    'proveedores.crear',
                    'tesoreria.ver',
                    'tesoreria.crear',
                    'contabilidad.ver',
                    'contabilidad.crear',
                    'activos.ver',
                    'activos.crear',
                    'tributario.ver',
                    'tributario.crear',
                    'usuarios.ver',
                    'usuarios.gestionar',
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

            $token = $user->createToken('react-spa-token')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id' => $user->rol_id,
                    'permisos' => $permisos
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error('Error en Login: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error Interno del Servidor. Inténtelo más tarde.'
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['empresa', 'rol']);
        $permisos = $user->rol->permisos ?? [];

        if ($user->rol && $user->rol->jerarquia >= 100) {
            $permisos = [
                'ventas.ver',
                'ventas.crear',
                'clientes.ver',
                'clientes.crear',
                'compras.ver',
                'compras.crear',
                'proveedores.ver',
                'proveedores.crear',
                'tesoreria.ver',
                'tesoreria.crear',
                'contabilidad.ver',
                'contabilidad.crear',
                'activos.ver',
                'activos.crear',
                'tributario.ver',
                'tributario.crear',
                'usuarios.ver',
                'usuarios.gestionar',
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

        $userData = $user->toArray();
        $userData['permisos'] = $permisos;

        return response()->json($userData);
    }
}