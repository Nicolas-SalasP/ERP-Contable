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
        $filtros = $request->only(['estado', 'search', 'num', 'limit', 'fecha_desde', 'fecha_hasta']);
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
                'numero_factura' => 'required|string|max:255',
                'tipo_documento' => 'required|string|in:FACTURA,NOTA_CREDITO,BOLETA,NOTA_DEBITO,COMPRA',
                'monto_bruto' => 'required|numeric|gt:0',
                'monto_neto' => 'required|numeric|gt:0',
                'cuentaDestino' => 'sometimes|string',
                'cuentaIva' => 'nullable|string',   
                'cuentaProveedor' => 'nullable|string',
                'centro_costo_id' => 'nullable|integer',
                'fecha_emision' => 'nullable|date',
                'fecha_vencimiento' => 'nullable|date',
                'factura_referencia_id' => 'nullable|integer',
            ], [
                'monto_bruto.gt' => 'El monto bruto debe ser mayor a 0',
                'monto_neto.gt' => 'El monto neto debe ser mayor a 0',
            ]);

            if ($input['tipo_documento'] === 'NOTA_CREDITO' && !empty($input['factura_referencia_id'])) {
                $facturaOriginal = \App\Domains\Comercial\Models\Factura::where('empresa_id', $request->user()->empresa_id)
                    ->find($input['factura_referencia_id']);

                if (!$facturaOriginal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La factura referenciada no existe.'
                    ], 422);
                }

                if ((float) $input['monto_bruto'] > (float) $facturaOriginal->monto_bruto) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El monto de la nota de credito no puede ser mayor a la factura original (' .
                            $facturaOriginal->monto_bruto . ').'
                    ], 422);
                }
            }

            $datos = [
                'empresa_id' => $request->user()->empresa_id,
                'proveedor_id' => $input['proveedor_id'] ?? null,
                'cuenta_bancaria_id' => $input['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $input['numero_factura'],
                'fecha_emision' => $input['fecha_emision'] ?? now()->toDateString(),
                'fecha_vencimiento' => $input['fecha_vencimiento'] ?? null,
                'monto_neto' => $input['monto_neto'],
                'monto_iva' => $input['monto_iva'] ?? 0,
                'monto_bruto' => $input['monto_bruto'],
                'tipo_documento' => $input['tipo_documento'],
                'autorizador_id' => $input['autorizador_id'] ?? null,
                'motivo_correccion' => $input['motivoCorreccion'] ?? null,
                'cuentaDestino' => $input['cuentaDestino'] ?? null,
                'cuentaIva' => $input['cuentaIva'] ?? null,
                'cuentaProveedor' => $input['cuentaProveedor'] ?? null,
                'centro_costo_id' => $input['centro_costo_id'] ?? null,
                'factura_referencia_id' => $input['factura_referencia_id'] ?? null,
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

    public function anular(Request $request, $id)
    {
        try {
            $request->validate(['motivo' => 'required|string|min:5']);

            $this->service->anularFactura(
                $request->user()->empresa_id,
                $request->user()->id,
                (int) $id,
                $request->input('motivo')
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura anulada con éxito y contabilidad reversada.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function vencidas(Request $request)
    {
        try {
            $vencidas = $this->service->obtenerVencidas($request->user()->empresa_id);
            return response()->json([
                'success' => true,
                'data' => $vencidas
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function exportarExcel(Request $request)
    {
        try {
            $csvContent = $this->service->generarCsvExportacion($request->user()->empresa_id);
            
            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="reporte_facturas_' . date('Y_m_d') . '.csv"',
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function disponiblesProyectos(Request $request)
    {
        try {
            $facturas = $this->service->obtenerFacturasDisponiblesParaProyectos(
                $request->user()->empresa_id
            );
            return response()->json(['success' => true, 'data' => $facturas]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function vincularProyecto(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'proyecto_id' => 'sometimes|integer',
                'proyecto_activo_id' => 'sometimes|integer',
            ]);

            $proyectoId = $datos['proyecto_activo_id'] ?? $datos['proyecto_id'] ?? null;
            if (!$proyectoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes especificar proyecto_id o proyecto_activo_id.',
                    'errors' => ['proyecto_id' => ['Campo requerido.']]
                ], 422);
            }

            $this->service->vincularAProyecto(
                $request->user()->empresa_id,
                (int) $id,
                (int) $proyectoId
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura vinculada al proyecto exitosamente.'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }
}