<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\ClienteService;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Exception;

class ClienteController
{
    protected $service;

    public function __construct(ClienteService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->buscarClientesPorEmpresa($request->user()->empresa_id, $request->search)
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $cliente = \App\Domains\Comercial\Models\Cliente::where('empresa_id', $request->user()->empresa_id)
                ->find($id);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $cliente
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'rut' => 'required|string|max:20',
                'razon_social' => 'required|string|max:255',
            ]);

            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'rut' => $request->rut,
                'razon_social' => $request->razonSocial ?? $request->razon_social,
                'contacto_nombre' => $request->nombre_contacto ?? $request->contacto_nombre ?? null,
                'contacto_email' => $request->email_contacto ?? $request->contacto_email ?? null,
                'contacto_telefono' => $request->telefono_contacto ?? $request->contacto_telefono ?? null,
                'direccion' => $request->direccion_comercial ?? $request->direccion ?? null,
                'telefono' => $request->telefono ?? null,
                'email' => $request->email_facturacion ?? $request->email ?? null,

                'estado' => 'ACTIVO'
            ];

            $cliente = $this->service->registrarCliente($datos);

            return response()->json(['success' => true, 'data' => $cliente], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'rut' => [
                    'sometimes',
                    'string',
                    Rule::unique('clientes', 'rut')
                        ->where('empresa_id', $request->user()->empresa_id)
                        ->ignore($id)
                ],
                'razon_social' => 'sometimes|string',
                'razonSocial' => 'sometimes|string',
            ]);
            $datos = [];

            if ($request->has('rut'))
                $datos['rut'] = $request->rut;
            if ($request->has('razonSocial'))
                $datos['razon_social'] = $request->razonSocial;
            if ($request->has('razon_social'))
                $datos['razon_social'] = $request->razon_social;
            if ($request->has('estado')) {
                $est = $request->estado;
                $datos['estado'] = ($est === true || $est === 'true' || $est == 1 || $est === 'ACTIVO') ? 'ACTIVO' : 'INACTIVO';
            }
            if ($request->has('activo')) {
                $datos['estado'] = filter_var($request->activo, FILTER_VALIDATE_BOOLEAN) ? 'ACTIVO' : 'INACTIVO';
            }
            if ($request->has('nombre_contacto'))
                $datos['contacto_nombre'] = $request->nombre_contacto;
            if ($request->has('contacto_nombre'))
                $datos['contacto_nombre'] = $request->contacto_nombre;
            if ($request->has('email_contacto'))
                $datos['contacto_email'] = $request->email_contacto;
            if ($request->has('contacto_email'))
                $datos['contacto_email'] = $request->contacto_email;
            if ($request->has('telefono_contacto'))
                $datos['contacto_telefono'] = $request->telefono_contacto;
            if ($request->has('contacto_telefono'))
                $datos['contacto_telefono'] = $request->contacto_telefono;
            if ($request->has('direccion_comercial'))
                $datos['direccion'] = $request->direccion_comercial;
            if ($request->has('direccion'))
                $datos['direccion'] = $request->direccion;
            if ($request->has('email_facturacion'))
                $datos['email'] = $request->email_facturacion;
            if ($request->has('email'))
                $datos['email'] = $request->email;
            if ($request->has('telefono'))
                $datos['telefono'] = $request->telefono;

            $cliente = $this->service->actualizarCliente($id, $datos);

            return response()->json(['success' => true, 'data' => $cliente]);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Errores de validación', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $this->service->inactivarCliente($request->user()->empresa_id, $id);
        return response()->json(['success' => true]);
    }

    public function activar(Request $request, $id)
    {
        try {
            $cliente = $this->service->activarCliente($request->user()->empresa_id, $id);
            return response()->json(['success' => true, 'message' => 'Cliente activado']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function reactivar(Request $request, $id)
    {
        try {
            $cliente = $this->service->reactivarCliente($request->user()->empresa_id, (int) $id);

            return response()->json([
                'success' => true,
                'message' => 'Cliente reactivado exitosamente.',
                'data' => $cliente
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}