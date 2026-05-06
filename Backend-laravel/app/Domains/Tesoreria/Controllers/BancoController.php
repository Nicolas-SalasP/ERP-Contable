<?php

namespace App\Domains\Tesoreria\Controllers;

use App\Domains\Tesoreria\Services\BancoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        try {
            $cuentas = $this->service->obtenerCuentasPorEmpresa($request->user()->empresa_id);
            return response()->json([
                'success' => true,
                'data' => $cuentas
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function storeCuenta(Request $request)
    {
        try {
            $datos = $request->validate([
                'banco' => 'required|string|max:100',
                'numero_cuenta' => 'required|string|max:50',
                'tipo_cuenta' => 'required|string|max:50',
                'titular' => 'required|string|max:150',
                'rut_titular' => 'required|string|max:20',
            ]);

            $datos['empresa_id'] = $request->user()->empresa_id;

            $cuenta = $this->service->registrarCuentaPropia($datos);

            return response()->json([
                'success' => true,
                'message' => 'Cuenta bancaria registrada exitosamente',
                'data' => $cuenta
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
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

    public function ingresoManual(Request $request)
    {
        try {
            $datos = $request->validate([
                'cuenta_bancaria_id' => 'required|integer',
                'fecha' => 'required|date',
                'monto' => 'required|numeric|min:1',
                'tipo_movimiento' => 'required|string',
                'descripcion' => 'required|string|max:255',
            ]);

            $resultado = $this->service->registrarIngresoManual($request->user()->empresa_id, $datos);

            return response()->json([
                'success' => true,
                'message' => 'Ingreso manual registrado correctamente.',
                'data' => $resultado
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 403 ? 403 : 422;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function importarCartola(Request $request)
    {
        try {
            $request->validate([
                'cuenta_bancaria_id' => 'required|integer',
                'cuenta_contrapartida' => 'required|string',
                'archivo' => 'required|file|mimes:csv,txt'
            ]);

            $resultado = $this->service->procesarCartola(
                $request->user()->empresa_id, 
                $request->user()->id,
                $request->cuenta_bancaria_id, 
                $request->cuenta_contrapartida, 
                $request->file('archivo')
            );

            return response()->json([
                'success' => true,
                'message' => "Proceso completado. Importados: {$resultado['importados']} | Ignorados (Duplicados): {$resultado['ignorados']}",
                'data' => $resultado
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function movimientos(Request $request, $idCuenta)
    {
        try {
            $movimientos = $this->service->obtenerMovimientosPorCuenta(
                $request->user()->empresa_id, 
                $idCuenta
            );
            
            return response()->json([
                'success' => true,
                'data' => $movimientos
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }
}