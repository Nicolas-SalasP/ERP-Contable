<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\ProveedorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Exception;

class ProveedorController
{
    protected $service;

    public function __construct(ProveedorService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 15);
        $paginador = $this->service->obtenerProveedoresPaginados($request->user()->empresa_id, $limit);

        return response()->json([
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'totalPages' => $paginador->lastPage(),
                'page' => $paginador->currentPage()
            ]
        ]);
    }

    public function catalogo(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->obtenerCatalogoBasico($request->user()->empresa_id)
        ]);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_id;

            $proveedor = $this->service->registrarProveedor($datos);

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'codigo_generado' => $proveedor->codigo_interno
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function ficha(Request $request, $id)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerFichaProveedor($request->user()->empresa_id, (int) $id)
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $datos = [];
            if ($request->has('rut'))
                $datos['rut'] = $request->rut;
            if ($request->has('razonSocial'))
                $datos['razon_social'] = $request->razonSocial;
            if ($request->has('paisIso'))
                $datos['pais_iso'] = $request->paisIso;
            if ($request->has('moneda'))
                $datos['moneda_defecto'] = $request->moneda;
            if ($request->has('nombreContacto'))
                $datos['nombre_contacto'] = $request->nombreContacto;
            if ($request->has('emailContacto'))
                $datos['email_contacto'] = $request->emailContacto;
            if ($request->has('direccion'))
                $datos['direccion'] = $request->direccion;
            if ($request->has('telefono'))
                $datos['telefono'] = $request->telefono;

            $proveedor = $this->service->actualizarProveedor($request->user()->empresa_id, $id, $datos);

            return response()->json(['success' => true, 'data' => $proveedor]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function guardarAnticipo(Request $request)
    {
        try {
            $datosValidados = $request->validate([
                'proveedor_id' => 'required|integer',
                'fecha' => 'required|date',
                'monto' => 'required|numeric|min:1',
                'referencia' => 'nullable|string|max:255'
            ]);

            $anticipo = $this->service->registrarAnticipo(
                $request->user()->empresa_id,
                $datosValidados
            );

            return response()->json([
                'success' => true,
                'message' => 'Anticipo registrado correctamente.',
                'data' => $anticipo
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan datos obligatorios',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => $e->getMessage()
            ], 400);
        }
    }

    public function subirPdfAnticipo(Request $request, $id)
    {
        $rutaPdf = null;
        try {
            $request->validate(['pdf' => 'required|mimes:pdf|max:10240']);

            if ($request->hasFile('pdf')) {
                $path = $request->file('pdf')->store('anticipos_proveedores/pdfs', 'public');
                $rutaPdf = 'storage/' . $path;
            }

            $anticipo = $this->service->adjuntarPdfAnticipo($request->user()->empresa_id, $id, $rutaPdf);

            return response()->json(['success' => true, 'data' => $anticipo]);
        } catch (Exception $e) {
            if ($rutaPdf) {
                $pathToDelete = str_replace('storage/', '', $rutaPdf);
                Storage::disk('public')->delete($pathToDelete);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function cruzarDocumentos(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'facturas_ids' => 'required|array',
                'notas_credito_ids' => 'nullable|array',
                'anticipos_ids' => 'nullable|array',
            ]);

            $resultado = $this->service->compensarPartidas(
                $request->user()->empresa_id,
                $request->user()->id,
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Documentos cruzados y compensados exitosamente.',
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}