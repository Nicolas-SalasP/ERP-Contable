<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Services\UsuarioService;
use Illuminate\Http\Request;
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
            $datos = $request->validate([
                'email' => 'required|email',
                'rol_id' => 'required|integer'
            ]);

            $this->service->invitarUsuario(
                $request->user()->empresa_id,
                $datos['email'],
                $datos['rol_id']
            );

            return response()->json(['success' => true, 'message' => 'Invitación enviada.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function actualizarRol(Request $request, $id)
    {
        try {
            $datos = $request->validate(['rol_id' => 'required|integer']);

            $this->service->actualizarRol(
                $request->user()->empresa_id,
                $id,
                $datos['rol_id']
            );

            return response()->json(['success' => true, 'message' => 'Rol actualizado.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al actualizar rol'], 400);
        }
    }

    public function desvincular(Request $request, $id)
    {
        try {
            $this->service->desvincularUsuario($request->user()->empresa_id, $id);
            return response()->json(['success' => true, 'message' => 'Usuario desvinculado.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al desvincular'], 400);
        }
    }

    public function storeRol(Request $request)
    {
        $datos = $request->validate([
            'nombre' => 'required|string',
            'permisos' => 'nullable|array'
        ]);
        $rol = $this->service->guardarRol($request->user()->empresa_id, $datos);
        return response()->json(['success' => true, 'data' => $rol]);
    }

    public function updateRol(Request $request, $id)
    {
        $datos = $request->validate([
            'nombre' => 'required|string',
            'permisos' => 'nullable|array'
        ]);
        $rol = $this->service->actualizarRolPermisos($id, $datos);
        return response()->json(['success' => true, 'data' => $rol]);
    }
}