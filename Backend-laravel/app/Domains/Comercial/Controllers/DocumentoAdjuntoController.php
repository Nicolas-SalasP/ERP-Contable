<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Services\DocumentoAdjuntoService;
use App\Support\MensajeErrorGenerico;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @tags Comercial - Documentos Adjuntos
 */
class DocumentoAdjuntoController
{
    protected $service;

    public function __construct(DocumentoAdjuntoService $service)
    {
        $this->service = $service;
    }

    /** Lista los documentos adjuntos de una factura. */
    public function index(Request $request, int $facturaId)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->service->listar($request->user()->empresa_activa_id, $facturaId),
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 404);
        }
    }

    /** Sube uno o varios documentos (PDF o imagen) a una factura. */
    public function store(Request $request, int $facturaId)
    {
        try {
            $request->validate([
                'documentos' => 'required|array|min:1|max:10',
                'documentos.*' => 'file|mimes:pdf,jpg,jpeg,png,webp|max:15360',
            ]);

            $creados = $this->service->subir(
                $request->user()->empresa_activa_id,
                $request->user()->id,
                $facturaId,
                $request->file('documentos')
            );

            return response()->json([
                'success' => true,
                'message' => count($creados) === 1 ? 'Documento adjuntado correctamente.' : count($creados) . ' documentos adjuntados correctamente.',
                'data' => $creados,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e), 'errors' => $e->errors()], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    /** Descarga/muestra un documento adjunto puntual. */
    public function show(Request $request, int $facturaId, int $adjuntoId)
    {
        try {
            $adjunto = $this->service->obtenerParaDescarga($request->user()->empresa_activa_id, $facturaId, $adjuntoId);
            return Storage::disk('local')->response($adjunto->ruta, $adjunto->nombre_original, [
                'Content-Type' => $adjunto->mime_type,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 404);
        }
    }

    /** Elimina un documento adjunto: borra la fila y el archivo físico del servidor. */
    public function destroy(Request $request, int $facturaId, int $adjuntoId)
    {
        try {
            $this->service->eliminar($request->user()->empresa_activa_id, $facturaId, $adjuntoId);
            return response()->json(['success' => true, 'message' => 'Documento eliminado.']);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }
}
