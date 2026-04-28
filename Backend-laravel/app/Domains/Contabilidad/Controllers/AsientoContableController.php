<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\AsientoContableService;
use Illuminate\Http\Request;
use Exception;

class AsientoContableController
{
    protected $service;

    public function __construct(AsientoContableService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return $this->service->obtenerAsientosPaginados($request->user()->empresa_id);
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->all();
            $datos['empresa_id'] = $request->user()->empresa_id;

            $asiento = $this->service->registrarAsiento($datos, $request->detalles);

            return response()->json([
                'success' => true,
                'message' => 'Asiento contable registrado con éxito',
                'data' => $asiento->load('detalles')
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function storeAvanzado(Request $request)
    {
        try {
            $datos = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string|min:3',
                'detalles' => 'required|array|min:2',
                'detalles.*.cuenta_contable' => 'required|string',
                'detalles.*.debe' => 'required|numeric',
                'detalles.*.haber' => 'required|numeric'
            ]);

            $cabecera = [
                'empresa_id' => $request->user()->empresa_id,
                'usuario_id' => $request->user()->id,
                'fecha' => $datos['fecha'],
                'glosa' => $datos['glosa'],
                'tipo_asiento' => 'traspaso',
                'origen_modulo' => 'contabilidad',
                'estado' => 'MAYORIZADO',
            ];

            $asiento = $this->service->registrarAsiento($cabecera, $datos['detalles']);

            return response()->json([
                'success' => true,
                'message' => 'Asiento contable registrado exitosamente.',
                'data' => $asiento
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $asiento = $this->service->obtenerAsientoPorId($request->user()->empresa_id, $id);
            return response()->json([
                'success' => true,
                'data' => [
                    'cabecera' => $asiento,
                    'detalles' => $asiento->detalles->map(function ($d) {
                        return [
                            'cuenta_contable' => $d->cuenta_contable,
                            'cuenta_nombre' => $d->cuenta->nombre ?? 'Sin nombre',
                            'debe' => $d->debe,
                            'haber' => $d->haber,
                        ];
                    })
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'El asiento contable no existe o no pertenece a tu empresa.'
            ], 404);
        }
    }
}