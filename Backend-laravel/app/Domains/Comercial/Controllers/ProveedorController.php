<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Comercial\Services\ProveedorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Exception;

/**
 * @tags Proveedores
 */
class ProveedorController
{
    protected $service;

    public function __construct(ProveedorService $service)
    {
        $this->service = $service;
    }

    /** Lista paginada de proveedores de la empresa (?limit). */
    public function index(Request $request)
    {
        $limit = (int) $request->query('limit', 15);
        $paginador = $this->service->obtenerProveedoresPaginados($request->user()->empresa_activa_id, $limit);

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

    /** Catalogo basico de proveedores (id + razon social) para selectores. */
    public function catalogo(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->obtenerCatalogoBasico($request->user()->empresa_activa_id)
        ]);
    }

    /** Crea un proveedor (genera codigo interno automatico). */
    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_activa_id;

            $proveedor = $this->service->registrarProveedor($datos);

            return response()->json([
                'success' => true,
                'data' => $proveedor,
                'codigo_generado' => $proveedor->codigo_interno
            ], 201);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
            ], 422);
        }
    }

    /** Ficha completa de un proveedor (datos + cuentas + anticipos). */
    public function ficha(Request $request, $id)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->obtenerFichaProveedor($request->user()->empresa_activa_id, (int) $id)
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
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
            if ($request->has('region'))
                $datos['region'] = $request->region;
            if ($request->has('comuna'))
                $datos['comuna'] = $request->comuna;

            $proveedor = $this->service->actualizarProveedor($request->user()->empresa_activa_id, $id, $datos);

            return response()->json(['success' => true, 'data' => $proveedor]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 422);
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
                $request->user()->empresa_activa_id,
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
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
            ], 400);
        }
    }

    public function subirPdfAnticipo(Request $request, $id)
    {
        $rutaPdf = null;
        try {
            $request->validate(['pdf' => 'required|mimes:pdf|max:10240']);

            if ($request->hasFile('pdf')) {
                // Disco 'local' (privado): el comprobante tiene montos/proveedor y no debe ser servible por URL directa (ver descargarPdfAnticipo()).
                $rutaPdf = $request->file('pdf')->store('anticipos_proveedores/pdfs', 'local');
            }

            $anticipo = $this->service->adjuntarPdfAnticipo($request->user()->empresa_activa_id, $id, $rutaPdf);

            return response()->json(['success' => true, 'data' => $anticipo]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e), 'errors' => $e->errors()], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            if ($rutaPdf) {
                Storage::disk('local')->delete($rutaPdf);
            }

            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    /** Descarga el PDF de un anticipo, autenticado y acotado a la empresa. GET /proveedores/anticipos/{id}/pdf */
    public function descargarPdfAnticipo(Request $request, int $id)
    {
        $anticipo = \App\Domains\Comercial\Models\AnticipoProveedor::where('empresa_id', $request->user()->empresa_activa_id)
            ->findOrFail($id);

        if (!$anticipo->archivo_pdf || !Storage::disk('local')->exists($anticipo->archivo_pdf)) {
            return response()->json(['success' => false, 'message' => 'No hay PDF adjunto para este anticipo.'], 404);
        }

        return Storage::disk('local')->response($anticipo->archivo_pdf, "Anticipo-{$anticipo->id}.pdf");
    }

    /** Inactiva (bloquea) un proveedor. */
    public function destroy(Request $request, $id)
    {
        $this->service->inactivarProveedor($request->user()->empresa_activa_id, $id);
        return response()->json(['success' => true]);
    }

    /** Marca un proveedor como activo (desbloquea). */
    public function activar(Request $request, $id)
    {
        try {
            $this->service->activarProveedor($request->user()->empresa_activa_id, $id);
            return response()->json(['success' => true, 'message' => 'Proveedor activado']);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
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
                $request->user()->empresa_activa_id,
                $request->user()->id,
                (int) $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Documentos cruzados y compensados exitosamente.',
                'data' => $resultado
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors()
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
            ], 400);
        }
    }
}