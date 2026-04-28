<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\BancoService;
use Illuminate\Http\Request;
use Exception;

class BancoController
{
    protected $service;

    public function __construct(BancoService $service)
    {
        $this->service = $service;
    }

    public function catalogo()
    {
        return response()->json($this->service->obtenerCatalogo());
    }

    public function cuentasEmpresa(Request $request)
    {
        $cuentas = $this->service->obtenerCuentasPorEmpresa($request->user()->empresa_id);
        return response()->json($cuentas);
    }

    public function storeCuenta(Request $request)
    {
        try {
            $datos = $request->validate([
                'banco' => 'required|string',
                'numero_cuenta' => 'required|string',
                'tipo_cuenta' => 'required|string',
                'titular' => 'required|string',
                'rut_titular' => 'required|string',
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $cuenta = $this->service->registrarCuentaPropia($datos);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta bancaria registrada exitosamente',
                'data' => $cuenta
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function pagarNomina(Request $request)
    {
        $request->validate([
            'facturas_ids' => 'required|array',
            'cuenta_bancaria_id' => 'required|integer'
        ]);

        try {
            $resultado = $this->service->pagarNominaMasiva(
                $request->user()->empresa_id,
                $request->user()->id,
                $request->facturas_ids,
                $request->cuenta_bancaria_id
            );
            
            return response()->json(array_merge(['success' => true], $resultado));
            
        } catch (Exception $e) {
            return response()->json(['success' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }
}