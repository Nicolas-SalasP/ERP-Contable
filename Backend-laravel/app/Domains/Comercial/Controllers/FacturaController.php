<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\Factura;
use App\Domains\Comercial\Services\FacturaService;
use App\Domains\Core\Models\Empresa;
use App\Support\MensajeErrorGenerico;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * @tags Facturas
 */
class FacturaController
{
    protected $service;

    public function __construct(FacturaService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filtros = $request->only(['estado', 'tipo', 'proveedor_id', 'cliente_id', 'search', 'num', 'limit', 'fecha_desde', 'fecha_hasta']);
        $paginador = $this->service->obtenerFacturasPaginadas($request->user()->empresa_activa_id, $filtros);

        return response()->json([
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'totalPages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ]);
    }

    public function historial(Request $request)
    {
        $filtros = $request->only(['estado', 'search', 'num', 'limit', 'fecha_desde', 'fecha_hasta']);
        $paginador = $this->service->obtenerFacturasPaginadas($request->user()->empresa_activa_id, $filtros);

        return response()->json([
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'totalPages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ]);
    }

    public function check(Request $request)
    {
        $existe = $this->service->verificarDuplicado(
            $request->user()->empresa_activa_id,
            (int) ($request->query('proveedorId') ?? $request->query('proveedor_id')),
            $request->query('numeroFactura') ?? $request->query('numero_factura')
        );

        return response()->json([
            'success' => true,
            'exists' => $existe,
        ]);
    }

    public function show(Request $request, $id)
    {
        try {
            $factura = $this->service->obtenerFacturaPorId($request->user()->empresa_activa_id, (int) $id);

            return response()->json([
                'success' => true,
                'data' => $factura,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 404);
        }
    }

    /** Registra una factura de compra y centraliza su asiento contable. */
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
            'autorizadorId' => 'autorizador_id',
            'esDocumentoExterior' => 'es_documento_exterior',
            'tipoCambio' => 'tipo_cambio',
            'montoBrutoOrigen' => 'monto_bruto_origen',
            'tipoGastoArt59' => 'tipo_gasto_art59',
        ];

        $input = $request->all();

        foreach ($mapeo as $camel => $snake) {
            if (isset($input[$camel]) && ! isset($input[$snake])) {
                $input[$snake] = $input[$camel];
            }
        }

        $request->merge($input);

        try {
            $request->validate([
                'numero_factura' => 'required|string|max:255',
                'tipo_documento' => 'required|string|in:FACTURA,NOTA_CREDITO,BOLETA,NOTA_DEBITO,COMPRA,FACTURA_EXTERIOR',
                'monto_bruto' => 'required|numeric|gt:0',
                'monto_neto' => 'required|numeric|gt:0',
                'cuentaDestino' => 'sometimes|string',
                'cuentaIva' => 'nullable|string',
                'cuentaProveedor' => 'nullable|string',
                'centro_costo_id' => 'nullable|integer',
                'fecha_emision' => 'nullable|date',
                'fecha_vencimiento' => 'nullable|date',
                'factura_referencia_id' => 'nullable|integer',
                'es_documento_exterior' => 'nullable|boolean',
                'tipo_cambio' => 'nullable|numeric|min:0',
                'monto_bruto_origen' => 'nullable|numeric|min:0',
                'moneda' => 'nullable|string|max:3',
                'tipo_gasto_art59' => 'nullable|string|in:intereses,regalias,servicios_tecnicos,honorarios,otros',
                'cuentaRetencion' => 'nullable|string|max:20',
            ], [
                'monto_bruto.gt' => 'El monto bruto debe ser mayor a 0',
                'monto_neto.gt' => 'El monto neto debe ser mayor a 0',
            ]);

            if ($input['tipo_documento'] === 'NOTA_CREDITO' && ! empty($input['factura_referencia_id'])) {
                $facturaOriginal = Factura::where('empresa_id', $request->user()->empresa_activa_id)
                    ->find($input['factura_referencia_id']);

                if (! $facturaOriginal) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La factura referenciada no existe.',
                    ], 422);
                }

                if ((float) $input['monto_bruto'] > (float) $facturaOriginal->monto_bruto) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El monto de la nota de credito no puede ser mayor a la factura original ('.
                            $facturaOriginal->monto_bruto.').',
                    ], 422);
                }
            }

            $datos = [
                'empresa_id' => $request->user()->empresa_activa_id,
                'proveedor_id' => $input['proveedor_id'] ?? null,
                'cuenta_bancaria_id' => $input['cuenta_bancaria_id'] ?? null,
                'numero_factura' => $input['numero_factura'],
                'fecha_emision' => $input['fecha_emision'] ?? now()->toDateString(),
                'fecha_vencimiento' => $input['fecha_vencimiento'] ?? null,
                'monto_neto' => $input['monto_neto'],
                'monto_bruto' => $input['monto_bruto'],
                'tipo_documento' => $input['tipo_documento'],
                'autorizador_id' => $input['autorizador_id'] ?? null,
                'motivo_correccion' => $input['motivoCorreccion'] ?? null,
                'cuentaDestino' => $input['cuentaDestino'] ?? null,
                'cuentaIva' => $input['cuentaIva'] ?? null,
                'cuentaProveedor' => $input['cuentaProveedor'] ?? null,
                'centro_costo_id' => $input['centro_costo_id'] ?? null,
                'factura_referencia_id' => $input['factura_referencia_id'] ?? null,
                'es_documento_exterior' => $input['es_documento_exterior'] ?? false,
                'tipo_cambio' => $input['tipo_cambio'] ?? null,
                'monto_bruto_origen' => $input['monto_bruto_origen'] ?? null,
                'moneda' => $input['moneda'] ?? 'CLP',
                'tipo_gasto_art59' => $input['tipo_gasto_art59'] ?? null,
                'cuentaRetencion' => $input['cuentaRetencion'] ?? null,
            ];

            // No forzar monto_iva a 0 cuando no viene: registrarFacturaCompra() usa isset()
            // para decidir si autocalcula el IVA a partir del neto (19%) o valida el que llega.
            // Forzarlo a 0 aca pisaba ese autocalculo y rechazaba facturas correctas.
            if (isset($input['monto_iva'])) {
                $datos['monto_iva'] = $input['monto_iva'];
            }

            $factura = $this->service->registrarFacturaCompra($datos);

            return response()->json([
                'success' => true,
                'data' => $factura,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 422);
        }
    }

    public function verAsiento(Request $request, $id)
    {
        try {
            $datosAsiento = $this->service->obtenerAsientoDeFactura($request->user()->empresa_activa_id, $id);

            return response()->json([
                'success' => true,
                'data' => $datosAsiento,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 404);
        }
    }

    public function reclasificarAsiento(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'fecha' => 'required|date',
                'glosa' => 'required|string',
                'cambios' => 'required|array',
            ]);

            $this->service->reclasificarAsiento(
                $request->user()->empresa_activa_id,
                $request->user()->id,
                $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Asiento reclasificado exitosamente.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    public function auditoria(Request $request, $id)
    {
        try {
            $data = $this->service->obtenerAuditoriaCompleta($request->user()->empresa_activa_id, $id);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener auditoría: '.MensajeErrorGenerico::desde($e),
            ], 404);
        }
    }

    /** Registra el pago de una factura. */
    public function pagar(Request $request, $id)
    {
        try {
            $factura = $this->service->registrarPago(
                $request->user()->empresa_activa_id,
                $id,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura pagada correctamente.',
                'data' => $factura,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    /** Anula una factura (genera asiento inverso). Requiere motivo. */
    public function anular(Request $request, $id)
    {
        try {
            $request->validate([
                'motivo' => 'required|string|min:5',
                'fecha_anulacion' => 'nullable|date_format:Y-m-d',
            ]);

            $this->service->anularFactura(
                $request->user()->empresa_activa_id,
                $request->user()->id,
                (int) $id,
                $request->input('motivo'),
                $request->input('fecha_anulacion')
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura anulada con éxito y contabilidad reversada.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    public function vencidas(Request $request)
    {
        try {
            $vencidas = $this->service->obtenerVencidas($request->user()->empresa_activa_id);

            return response()->json([
                'success' => true,
                'data' => $vencidas,
            ]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function exportarExcel(Request $request)
    {
        try {
            $request->validate([
                'fecha_desde' => 'nullable|date',
                'fecha_hasta' => 'nullable|date',
            ]);

            $csvContent = $this->service->generarCsvExportacion(
                $request->user()->empresa_activa_id,
                $request->query('fecha_desde'),
                $request->query('fecha_hasta'),
            );

            return response($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="reporte_facturas_'.date('Y_m_d').'.csv"',
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function disponiblesProyectos(Request $request)
    {
        try {
            $facturas = $this->service->obtenerFacturasDisponiblesParaProyectos(
                $request->user()->empresa_activa_id
            );

            return response()->json(['success' => true, 'data' => $facturas]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
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
            if (! $proyectoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes especificar proyecto_id o proyecto_activo_id.',
                    'errors' => ['proyecto_id' => ['Campo requerido.']],
                ], 422);
            }

            $this->service->vincularAProyecto(
                $request->user()->empresa_activa_id,
                (int) $id,
                (int) $proyectoId
            );

            return response()->json([
                'success' => true,
                'message' => 'Factura vinculada al proyecto exitosamente.',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;

            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], $status);
        }
    }

    /** Resumen de retenciones Art. 59 LIR para el Formulario 50. GET /facturas/f50?periodo=YYYY-MM */
    public function f50(Request $request)
    {
        $empresaId = $request->user()->empresa_activa_id;
        $periodo = $request->query('periodo', now()->format('Y-m'));

        [$anio, $mes] = explode('-', $periodo);

        $retenciones = DB::table('facturas')
            ->where('empresa_id', $empresaId)
            ->whereYear('fecha_emision', $anio)
            ->whereMonth('fecha_emision', $mes)
            ->whereNotNull('retencion_art59')
            ->where('retencion_art59', '>', 0)
            ->where('estado', '!=', 'ANULADA')
            ->whereNull('deleted_at')
            ->select(
                'tipo_gasto_art59',
                DB::raw('COUNT(*) as cantidad_facturas'),
                DB::raw('SUM(monto_bruto) as base_imponible'),
                DB::raw('SUM(retencion_art59) as total_retencion')
            )
            ->groupBy('tipo_gasto_art59')
            ->get();

        $tasas = [
            'intereses' => 4.0,
            'regalias' => 30.0,
            'servicios_tecnicos' => 15.0,
            'honorarios' => 15.0,
            'otros' => 35.0,
        ];

        $detalle = $retenciones->map(function (object $r) use ($tasas) {
            /** @var object{tipo_gasto_art59: string|null, cantidad_facturas: int, base_imponible: string|null, total_retencion: string|null} $r */
            return [
                'tipo_gasto' => $r->tipo_gasto_art59,
                'tasa_porcentaje' => $tasas[$r->tipo_gasto_art59 ?? ''] ?? null,
                'cantidad_facturas' => $r->cantidad_facturas,
                'base_imponible' => round((float) $r->base_imponible, 2),
                'total_retencion' => round((float) $r->total_retencion, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'periodo' => $periodo,
            'total_retener' => round($detalle->sum('total_retencion'), 2),
            'detalle' => $detalle->values(),
        ]);
    }

    /** Emite una Nota de Crédito sobre una factura de VENTA. POST /facturas/{id}/nota-credito */
    public function notaCredito(Request $request, int $id)
    {
        try {
            $datos = $request->validate([
                'numero_nc' => 'required|string|max:255',
                'monto_neto' => 'required|numeric|gt:0',
                'monto_iva' => 'required|numeric|min:0',
                'monto_bruto' => 'required|numeric|gt:0',
                'razon' => 'required|string|min:5|max:255',
                'fecha_emision' => 'nullable|date',
                'emitir_dte' => 'nullable|boolean',
            ]);

            $nc = $this->service->emitirNotaCreditoVenta(
                $request->user()->empresa_activa_id,
                $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Nota de Crédito emitida y contabilidad reversada.',
                'data' => $nc,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }

    /** Adjunta un PDF a una factura. POST /facturas/{id}/pdf */
    public function subirPdf(Request $request, int $id): JsonResponse
    {
        $rutaPdf = null;
        try {
            $request->validate(['pdf' => 'required|mimes:pdf|max:10240']);

            $empresaId = $request->user()->empresa_activa_id;
            $factura = Factura::where('empresa_id', $empresaId)->findOrFail($id);

            if ($request->hasFile('pdf')) {
                if ($factura->archivo_pdf) {
                    Storage::disk('local')->delete($factura->archivo_pdf);
                }
                // Disco 'local' (privado): un PDF de factura tiene RUT/montos/proveedor, no debe quedar servible por URL directa sin autenticación (ver descargarPdf()).
                $rutaPdf = $request->file('pdf')->store('facturas/pdfs', 'local');
            }

            $factura->archivo_pdf = $rutaPdf;
            $factura->save();

            return response()->json(['success' => true, 'archivo_pdf' => $rutaPdf]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e), 'errors' => $e->errors()], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    /** Descarga el PDF adjunto de una factura, autenticado y acotado a la empresa. GET /facturas/{id}/pdf */
    public function descargarPdf(Request $request, int $id)
    {
        $factura = Factura::where('empresa_id', $request->user()->empresa_activa_id)->find($id);

        if (! $factura) {
            return response()->json(['success' => false, 'message' => 'Factura no encontrada.'], 404);
        }

        if (! $factura->archivo_pdf || ! Storage::disk('local')->exists($factura->archivo_pdf)) {
            return response()->json(['success' => false, 'message' => 'No hay PDF adjunto para esta factura.'], 404);
        }

        return Storage::disk('local')->response($factura->archivo_pdf, "Factura-{$factura->numero_factura}.pdf");
    }

    /**
     * Genera el comprobante PDF de una factura de VENTA (documento interno, no el DTE
     * timbrado del SII). ?copia=1 marca el documento como reimpresión -- watermark
     * "COPIA" visible, para no confundirlo con la primera impresión/envío original.
     * GET /facturas/{id}/comprobante
     */
    public function generarComprobantePdf(Request $request, int $id)
    {
        $empresaId = $request->user()->empresa_activa_id;
        $factura = Factura::where('empresa_id', $empresaId)
            ->where('tipo', 'VENTA')
            ->with('cliente')
            ->find($id);

        if (! $factura) {
            return response()->json(['success' => false, 'message' => 'Factura de venta no encontrada.'], 404);
        }

        $empresa = Empresa::find($empresaId);
        $esCopia = $request->boolean('copia');

        $pdf = Pdf::loadView('pdf.factura', compact('factura', 'empresa', 'esCopia'));
        $sufijo = $esCopia ? '-COPIA' : '';

        return $pdf->download("Factura-{$factura->numero_factura}{$sufijo}.pdf");
    }

    /** Emite una Nota de Débito sobre una factura de VENTA. POST /facturas/{id}/nota-debito */
    public function notaDebito(Request $request, int $id)
    {
        try {
            $datos = $request->validate([
                'numero_nd' => 'required|string|max:255',
                'monto_neto' => 'required|numeric|gt:0',
                'monto_iva' => 'required|numeric|min:0',
                'monto_bruto' => 'required|numeric|gt:0',
                'razon' => 'required|string|min:5|max:255',
                'fecha_emision' => 'nullable|date',
                'emitir_dte' => 'nullable|boolean',
            ]);

            $nd = $this->service->emitirNotaDebitoVenta(
                $request->user()->empresa_activa_id,
                $id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Nota de Débito registrada correctamente.',
                'data' => $nd,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
            ], 400);
        }
    }
}
