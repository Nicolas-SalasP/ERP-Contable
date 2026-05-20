<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Domains\Core\Models\User;
use Illuminate\Validation\ValidationException;
use App\Domains\Core\Services\ProvisionUserService;
use App\Domains\Core\Services\WebAuthClient;
use Throwable;

class AuthController
{
    private const MODULE_PERMISSIONS_MAP = [
        'dashboard' => ['dashboard.ver'],
        'clientes' => ['ventas.ver', 'clientes.ver', 'clientes.crear'],
        'cotizaciones' => ['ventas.ver', 'ventas.crear'],
        'facturas.manual' => ['compras.ver', 'compras.crear'],
        'facturas.historial' => ['compras.ver'],
        'facturas.auditoria' => ['compras.ver'],
        'dte.emision' => ['ventas.ver', 'ventas.crear'],
        'documentos.anulacion' => ['ventas.ver', 'compras.ver'],
        'contabilidad.plan_cuentas' => ['contabilidad.ver', 'contabilidad.crear'],
        'contabilidad.libro_mayor' => ['contabilidad.ver'],
        'contabilidad.asientos' => ['contabilidad.ver', 'contabilidad.crear'],
        'contabilidad.visor' => ['contabilidad.ver'],
        'contabilidad.reclasificador' => ['contabilidad.ver', 'contabilidad.crear'],
        'proveedores' => ['compras.ver', 'proveedores.ver', 'proveedores.crear'],
        'tesoreria.cartola' => ['tesoreria.ver'],
        'tesoreria.conciliacion' => ['tesoreria.ver', 'tesoreria.crear'],
        'tesoreria.nomina' => ['tesoreria.ver', 'tesoreria.crear'],
        'inventario.productos' => ['inventario.productos.ver', 'inventario.productos.crear', 'inventario.productos.editar'],
        'inventario.bodegas' => ['inventario.bodegas.ver', 'inventario.bodegas.crear'],
        'inventario.movimientos' => ['inventario.movimientos.ver', 'inventario.movimientos.entrada', 'inventario.movimientos.salida', 'inventario.movimientos.traspaso', 'inventario.movimientos.ajuste'],
        'inventario.kardex' => ['inventario.kardex.ver'],
        'inventario.lotes' => ['inventario.lotes.ver', 'inventario.lotes.crear', 'inventario.lotes.editar'],
        'inventario.reservas' => ['inventario.reservas.ver', 'inventario.reservas.crear', 'inventario.reservas.cancelar', 'inventario.reservas.liberar', 'inventario.reservas.consumir'],
        'inventario.valorizacion' => ['inventario.valorizacion.ver'],
        'inventario.tomas_fisicas' => ['inventario.tomas_fisicas.ver', 'inventario.tomas_fisicas.crear', 'inventario.tomas_fisicas.contar', 'inventario.tomas_fisicas.cerrar', 'inventario.tomas_fisicas.ajustar', 'inventario.tomas_fisicas.cancelar'],
        'activos_fijos' => ['activos.ver', 'activos.crear'],
        'tributario.renta' => ['tributario.ver'],
        'tributario.mapeo_sii' => ['tributario.ver'],
        'tributario.f29' => ['tributario.ver', 'tributario.crear'],
        'usuarios.gestion' => ['usuarios.ver', 'usuarios.gestionar'],
        'roles.gestion' => ['usuarios.ver', 'usuarios.gestionar'],
        'empresa.perfil' => [],
        'glosario' => [],
        'integraciones.api' => [],
        'dashboard.ejecutivo' => ['contabilidad.ver', 'tesoreria.ver', 'ventas.ver'],
        'white_label' => [],
        'modulos.custom' => [],
    ];

    private function permisosDesdeModulos(array $moduleKeys): array
    {
        $permisos = [];
        foreach ($moduleKeys as $key) {
            $permisos = array_merge($permisos, self::MODULE_PERMISSIONS_MAP[$key] ?? []);
        }
        return array_values(array_unique($permisos));
    }

    public function __construct(
        private readonly WebAuthClient $webClient,
        private readonly ProvisionUserService $provisioner,
    ) {
    }

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

            if (!empty($user->module_keys)) {
                $permisos = $this->permisosDesdeModulos($user->module_keys);
            } elseif ($user->rol && $user->rol->jerarquia >= 100) {
                $permisos = $this->permisosDesdeModulos(array_keys(self::MODULE_PERMISSIONS_MAP));
            } else {
                $permisos = $user->rol->permisos ?? [];
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
                    'plan_slug' => $user->plan_slug,
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

    public function refresh(Request $request)
    {
        try {
            $user = $request->user();
            $tokenActual = $request->user()->currentAccessToken();
            $nuevoToken = $user->createToken('react-spa-token')->plainTextToken;
            if ($tokenActual) {
                $tokenActual->delete();
            }

            return response()->json([
                'success' => true,
                'token' => $nuevoToken,
                'issued_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $e) {
            Log::error('Error en refresh token: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token. Inicia sesion nuevamente.'
            ], 500);
        }
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['empresa', 'rol']);

        if (!empty($user->module_keys)) {
            $permisos = $this->permisosDesdeModulos($user->module_keys);
        } elseif ($user->rol && $user->rol->jerarquia >= 100) {
            $permisos = $this->permisosDesdeModulos(array_keys(self::MODULE_PERMISSIONS_MAP));
        } else {
            $permisos = $user->rol->permisos ?? [];
        }

        $userData = $user->toArray();
        $userData['permisos'] = $permisos;

        return response()->json($userData);
    }
}