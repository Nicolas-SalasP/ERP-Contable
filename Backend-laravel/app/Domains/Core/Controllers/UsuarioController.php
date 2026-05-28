<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Services\UsuarioService;
use App\Domains\Core\Models\User;
use App\Domains\Core\Models\Rol;
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

    public function roles()
    {
        try {
            $roles = $this->service->listarRoles();
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
            $usuarioDestino = User::with('rol')->findOrFail($id);
            $rolDestino = Rol::findOrFail($datos['rol_id']);

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
            $usuarioDestino = User::with('rol')->findOrFail($id);

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
                'permisos' => 'nullable|array'
            ]);
            $rol = $this->service->guardarRol($request->user()->empresa_id, $datos);
            return response()->json(['success' => true, 'data' => $rol]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateRol(Request $request, $id)
    {
        $datos = $request->validate([
            'nombre' => 'required|string|max:100',
            'permisos' => 'nullable|array'
        ]);
        $rol = $this->service->actualizarRolPermisos($id, $datos);
        return response()->json(['success' => true, 'data' => $rol]);
    }
}