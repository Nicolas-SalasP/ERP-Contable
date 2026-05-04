<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Services\FacturaService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Exception;

class FacturaController
{
    protected $service;

    public function __construct(FacturaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->obtenerFacturasPorEmpresa($request->user()->empresa_id, $request->query('estado'))
        ]);
    }

    public function historial(Request $request)
    {
        $filtros = $request->only(['estado', 'search', 'num', 'limit']);
        $paginador = $this->service->obtenerFacturasPaginadas($request->user()->empresa_id, $filtros);

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

    public function check(Request $request)
    {
        $existe = $this->service->verificarDuplicado(
            $request->user()->empresa_id,
            (int) ($request->query('proveedorId') ?? $request->query('proveedor_id')),
            $request->query('numeroFactura') ?? $request->query('numero_factura')
        );

        return response()->json([
            'success' => true,
            'exists' => $existe
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $factura = $this->service->obtenerFacturaPorId($request->user()->empresa_id, (int) $id);

            return response()->json([
                'success' => true,
                'data' => $factura
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function store(Request $request)
    {
        $mapeo = [
            'numeroFactura' => 'numero_factura',
            'tipoDocumento' => 'tipo_documento',
            'proveedorId' => 'proveedor_id',
            'cuentaBancariaId' => 'cuenta_bancaria_id',
            'fechaEmision' => 'fecha_emision',
            'fechaVencimiento' => 'fecha_vencimiento',
            'montoNeto' => 'monto_neto',
            'montoIva' => 'monto_iva',
            'montoBruto' => 'monto_bruto',
            'autorizadorId' => 'autorizador_id'
        ];

        $input = $request->all();

        foreach ($mapeo as $camel => $snake) {
            if (isset($input[$camel]) && !isset($input[$snake])) {
                $input[$snake] = $input[$camel];
            }
        }

        $request->merge($input);

        try {
            $request->validate([
                'numero_factura' => 'required|string',
                'tipo_documento' => 'required|string',
                'empresa_id' => 'nullable',
            ]);

            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'proveedor_id' => $input['proveedor_id'],
                'cuenta_bancaria_id' => $input['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $input['numero_factura'],
                'fecha_emision' => $input['fecha_emision'],
                'fecha_vencimiento' => $input['fecha_vencimiento'] ?? null,
                'monto_neto' => $input['monto_neto'],
                'monto_iva' => $input['monto_iva'],
                'monto_bruto' => $input['monto_bruto'],
                'tipo_documento' => $input['tipo_documento'],
                'autorizador_id' => $input['autorizador_id'] ?? null,
                'motivo_correccion' => $input['motivoCorreccion'] ?? null
            ];

            $factura = $this->service->registrarFacturaCompra($datos);

            return response()->json([
                'success' => true,
                'data' => $factura
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function verAsiento(Request $request, $id)
    {
        try {
            $datosAsiento = $this->service->obtenerAsientoDeFactura($request->user()->empresa_id, $id);

            return response()->json([
                'success' => true,
                'data' => $datosAsiento
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    public function reclasificarAsiento(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string',
                'cambios' => 'required|array'
            ]);

            $this->service->reclasificarAsiento(
                $request->user()->empresa_id,
                $request->user()->id,
                $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Asiento reclasificado exitosamente.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function auditoria($id)
    {
        try {
            $data = $this->service->obtenerAuditoriaCompleta($id);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al obtener auditoría: ' . $e->getMessage()
            ], 404);
        }
    }

    public function pagar(Request $request, $id)
    {
        try {
            $factura = $this->service->registrarPago(
                $request->user()->empresa_id,
                $id,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura pagada correctamente.',
                'data' => $factura
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}