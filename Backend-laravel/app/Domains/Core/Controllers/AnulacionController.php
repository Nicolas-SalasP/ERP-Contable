<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\Request;
use App\Domains\Core\Services\AnulacionService;
use Exception;

class AnulacionController
{
    protected $service;

    public function __construct(AnulacionService $service)
    {
        $this->service = $service;
    }

    public function buscar(Request $request)
    {
        try {
            $datos = $request->validate([
                'tipo_documento'   => 'required|string',
                'numero_documento' => 'required|string'
            ]);

            $documento = $this->service->buscarDocumento(
                $request->user()->empresa_id,
                $datos['tipo_documento'],
                $datos['numero_documento']
            );

            return response()->json([
                'success' => true,
                'data'    => $documento
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function anular(Request $request)
    {
        try {
            $datos = $request->validate([
                'tipo_documento'  => 'required|string',
                'documento_id'    => 'required|integer',
                'motivo'          => 'required|string|min:5',
                'fecha_anulacion' => 'required|date'
            ]);

            $resultado = $this->service->anularDocumento(
                $request->user()->empresa_id,
                $datos['tipo_documento'],
                $datos['documento_id'],
                $datos['motivo'],
                $request->user()->id,
                $datos['fecha_anulacion']
            );

            return response()->json([
                'success' => true,
                'message' => 'El documento fue anulado y su reverso generado exitosamente.',
                'data'    => $resultado
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}