<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Services\ImpuestosService;
use App\Support\MensajeErrorGenerico;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;

class ImpuestosController
{
    protected $service;

    public function __construct(ImpuestosService $service)
    {
        $this->service = $service;
    }

    public function simularF29(Request $request, $mes, $anio)
    {
        try {
            $mesInt = (int) $mes;
            $anioInt = (int) $anio;

            if ($mesInt < 1 || $mesInt > 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'El mes debe estar entre 1 y 12.'
                ], 422);
            }

            if ($anioInt < 2000 || $anioInt > 2100) {
                return response()->json([
                    'success' => false,
                    'message' => 'El año debe estar entre 2000 y 2100.'
                ], 422);
            }

            $resultado = $this->service->simularF29($request->user()->empresa_activa_id, $mesInt, $anioInt);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    /** Descarga el F29 del período en Excel (HTML-as-XLS) o PDF según ?formato=. */
    public function descargarF29(Request $request, int $mes, int $anio): Response
    {
        if ($mes < 1 || $mes > 12) {
            return response('El mes debe estar entre 1 y 12.', 422);
        }

        if ($anio < 2000 || $anio > 2100) {
            return response('El año debe estar entre 2000 y 2100.', 422);
        }

        $empresaId = $request->user()->empresa_activa_id;
        $simulacion = $this->service->simularF29($empresaId, $mes, $anio);
        $lineas = $simulacion['lineas_f29'];

        /** @var \stdClass|null $empresaRow */
        $empresaRow = DB::table('empresas')->where('id', $empresaId)->first();
        $empresa = [
            'razon_social' => $empresaRow->razon_social ?? '',
            'rut'          => $empresaRow->rut ?? '',
        ];

        $periodo = str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
        $formato = strtolower((string) $request->query('formato', 'excel'));

        if ($formato === 'pdf') {
            $pdf = Pdf::loadView('pdf.f29', compact('simulacion', 'empresa', 'lineas', 'periodo'))
                ->setPaper('a4', 'portrait');

            $nombre = sprintf('F29_%02d_%04d.pdf', $mes, $anio);

            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
            ]);
        }

        $html = view('pdf.f29_excel', compact('simulacion', 'empresa', 'lineas', 'periodo'))->render();
        $nombre = sprintf('F29_%02d_%04d.xls', $mes, $anio);

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}\"",
        ]);
    }

    public function ejecutarF29(Request $request)
    {
        try {
            $datos = $request->validate([
                'mes' => 'required|integer|between:1,12',
                'anio' => 'required|integer|min:2000|max:2100'
            ]);

            $asiento = $this->service->ejecutarF29(
                $request->user()->empresa_activa_id,
                $request->user()->id,
                $datos['mes'],
                $datos['anio']
            );

            return response()->json([
                'success' => true,
                'message' => 'Cierre F29 ejecutado correctamente.',
                'data' => $asiento
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => MensajeErrorGenerico::desde($e)
            ], 422);
        }
    }

    public function preCalculoRenta(Request $request, $anio)
    {
        try {
            $resultado = $this->service->preCalculoRenta($request->user()->empresa_activa_id, (int) $anio);
            return response()->json(['success' => true, 'data' => $resultado]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function obtenerMapeo(Request $request)
    {
        try {
            $data = $this->service->obtenerMapeo($request->user()->empresa_activa_id);
            return response()->json(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function guardarMapeo(Request $request)
    {
        try {
            $request->validate([
                'codigo_cuenta' => [
                    'required',
                    'string',
                    Rule::unique('mapeo_cuentas_sii')->where(function ($query) use ($request) {
                        return $query->where('empresa_id', $request->user()->empresa_activa_id);
                    })
                ],
                'concepto_sii' => 'required|string|in:INGRESOS_GIRO,OTROS_INGRESOS,COMPRAS,DEPRECIACION,REMUNERACIONES,HONORARIOS,ARRIENDOS,GASTOS_GENERALES'
            ]);

            $this->service->guardarMapeo(
                $request->user()->empresa_activa_id,
                $request->codigo_cuenta,
                $request->concepto_sii
            );

            return response()->json(['success' => true, 'message' => 'Mapeo guardado exitosamente.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 422);
        }
    }

    public function eliminarMapeo(Request $request, $id)
    {
        try {
            $this->service->eliminarMapeo($request->user()->empresa_activa_id, $id);
            return response()->json(['success' => true, 'message' => 'Mapeo eliminado.']);
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], $status);
        }
    }
}