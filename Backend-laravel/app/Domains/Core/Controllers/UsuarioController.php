<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Services\UsuarioService;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
use App\Domains\Core\Support\ModuloPermisos;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class UsuarioController
{
    protected $service;

    public function __construct(UsuarioService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $usuarios = $this->service->listarUsuarios($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $usuarios]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cargar usuarios'], 400);
        }
    }

    public function roles(Request $request)
    {
        try {
            $roles = $this->service->listarRoles($request->user()->empresa_id);
            return response()->json(['success' => true, 'data' => $roles]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al cargar roles'], 400);
        }
    }

    public function invitar(Request $request)
    {
        try {
            $miRol = $request->user()->load('rol')->rol;

            if (!$miRol || $miRol->jerarquia < 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para invitar usuarios.'
                ], 403);
            }

            $datos = $request->validate([
                'email' => 'required|email',
                'rol_id' => 'required|integer|exists:roles,id'
            ]);

            $this->service->invitarUsuario(
                $request->user()->empresa_id,
                $datos['email'],
                $datos['rol_id']
            );

            return response()->json(['success' => true, 'message' => 'Invitación enviada.']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function actualizarRol(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'rol_id' => 'required|integer|exists:roles,id'
            ]);

            $miRol = $request->user()->load('rol')->rol;
            $empresaId = $request->user()->empresa_id;

            // Scope multitenant: el usuario y el rol destino deben pertenecer a
            // la empresa del solicitante (el rol puede ser de sistema). Evita IDOR.
            $usuarioDestino = User::with('rol')->where('empresa_id', $empresaId)->findOrFail($id);
            $rolDestino = Rol::visiblesPara($empresaId)->findOrFail($datos['rol_id']);

            if ($usuarioDestino->rol && $usuarioDestino->rol->jerarquia >= $miRol->jerarquia && $request->user()->id !== $usuarioDestino->id) {
                return response()->json(['success' => false, 'message' => 'No puedes editar usuarios de jerarquía igual o superior.'], 403);
            }

            if ($rolDestino->jerarquia >= $miRol->jerarquia && $miRol->jerarquia < 100) {
                return response()->json(['success' => false, 'message' => 'Solo puedes asignar roles de menor jerarquía al tuyo.'], 403);
            }

            $this->service->actualizarRol($request->user()->empresa_id, $id, $datos['rol_id']);
            return response()->json(['success' => true, 'message' => 'Rol actualizado.']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar rol'], 400);
        }
    }

    public function desvincular(Request $request, $id)
    {
        try {
            $miRol = $request->user()->load('rol')->rol;

            // Scope multitenant: solo usuarios de la propia empresa. Evita IDOR.
            $usuarioDestino = User::with('rol')->where('empresa_id', $request->user()->empresa_id)->findOrFail($id);

            if ($request->user()->id === $usuarioDestino->id) {
                return response()->json(['success' => false, 'message' => 'No puedes eliminarte a ti mismo del sistema.'], 403);
            }

            if ($usuarioDestino->rol && $usuarioDestino->rol->jerarquia >= $miRol->jerarquia) {
                return response()->json(['success' => false, 'message' => 'No puedes desvincular a este usuario.'], 403);
            }

            $this->service->desvincularUsuario($request->user()->empresa_id, $id);
            return response()->json(['success' => true, 'message' => 'Usuario desvinculado.']);
            
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al desvincular'], 400);
        }
    }

    public function storeRol(Request $request)
    {
        try {
            $datos = $request->validate([
                'nombre' => 'required|string|max:100',
                'jerarquia' => 'nullable|integer|min:1|max:100',
                'permisos' => 'nullable|array',
                'permisos.*' => 'string',
            ]);

            if ($errorAutorizacion = $this->validarRolEditable($request->user(), $datos)) {
                return $errorAutorizacion;
            }

            $rol = $this->service->guardarRol($request->user()->empresa_id, $datos);
            return response()->json(['success' => true, 'data' => $rol]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al crear el rol'], 500);
        }
    }

    public function updateRol(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'nombre' => 'required|string|max:100',
                'jerarquia' => 'nullable|integer|min:1|max:100',
                'permisos' => 'nullable|array',
                'permisos.*' => 'string',
            ]);

            if ($errorAutorizacion = $this->validarRolEditable($request->user(), $datos)) {
                return $errorAutorizacion;
            }

            // El servicio solo permite editar roles propios de la empresa
            // (nunca de sistema ni de otra empresa).
            $rol = $this->service->actualizarRolPermisos($request->user()->empresa_id, $id, $datos);
            return response()->json(['success' => true, 'data' => $rol]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Rol no encontrado o no editable.'], 404);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar el rol'], 500);
        }
    }

    /**
     * Evita la escalada de privilegios al crear/editar un rol:
     *  - No se puede crear un rol de jerarquia igual o superior a la propia
     *    (salvo Super Admin, jerarquia >= 100).
     *  - No se pueden asignar permisos que el solicitante no posee
     *    (salvo Super Admin, que los tiene todos).
     *
     * @return \Illuminate\Http\JsonResponse|null  Respuesta 403 si no autorizado, null si OK.
     */
    private function validarRolEditable(User $solicitante, array $datos)
    {
        $miRol = $solicitante->load('rol')->rol;
        $miJerarquia = (int) ($miRol->jerarquia ?? 0);
        $esSuperAdmin = $miJerarquia >= 100;

        $nuevaJerarquia = (int) ($datos['jerarquia'] ?? 10);
        if (!$esSuperAdmin && $nuevaJerarquia >= $miJerarquia) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes crear o editar un rol de jerarquía igual o superior a la tuya.',
            ], 403);
        }

        if (!$esSuperAdmin) {
            $misPermisos = ModuloPermisos::permisosUsuario($solicitante);
            $solicitados = ModuloPermisos::normalizarLista($datos['permisos'] ?? []);
            $excedentes = array_values(array_diff($solicitados, $misPermisos));

            if (!empty($excedentes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes asignar permisos que tú no posees.',
                    'errors' => ['permisos' => $excedentes],
                ], 403);
            }
        }

        return null;
    }
}