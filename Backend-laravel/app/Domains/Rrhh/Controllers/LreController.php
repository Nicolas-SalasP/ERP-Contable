<?php

namespace App\Domains\Rrhh\Controllers;

use App\Domains\Rrhh\Exceptions\RrhhException;
use App\Domains\Rrhh\Models\LreEnvio;
use App\Domains\Rrhh\Services\Lre\ConfirmarDtService;
use App\Domains\Rrhh\Services\Lre\GenerarLreService;
use App\Domains\Rrhh\Services\Lre\PrepararDescargaService;
use App\Domains\Rrhh\Services\Lre\ValidarLreService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** R7 — Libro de Remuneraciones Electrónico (LRE): el archivo NO se transmite al SII/DT desde aquí, el empleador lo sube manualmente al portal Mi DT y registra el número de confirmación. */
class LreController extends Controller
{
    public function __construct(
        private readonly GenerarLreService      $generarService,
        private readonly ValidarLreService      $validarService,
        private readonly PrepararDescargaService $descargaService,
        private readonly ConfirmarDtService     $confirmarService,
    ) {}

    /** Genera el archivo LRE del período indicado y persiste el registro. */
    public function generar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'anio' => 'required|integer|min:2020|max:2100',
            'mes'  => 'required|integer|min:1|max:12',
        ]);

        $anio = (int) $datos['anio'];
        $mes  = (int) $datos['mes'];

        try {
            $lre = $this->generarService->generar($request->user()->empresa_activa_id, $anio, $mes);
        } catch (RrhhException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return response()->json($lre);
    }

    /** Valida el archivo LRE y actualiza su estado. */
    public function validar(Request $request, int $id): JsonResponse
    {
        $lre = LreEnvio::where('empresa_id', $request->user()->empresa_activa_id)
            ->find($id);

        if (! $lre) {
            return response()->json(['mensaje' => 'El LRE no existe o no pertenece a la empresa.'], 404);
        }

        try {
            $errores = $this->validarService->validar($lre);
        } catch (RrhhException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        if (empty($errores)) {
            $lre->update(['estado' => LreEnvio::ESTADO_VALIDADO]);

            return response()->json([
                'lre'    => $lre->fresh(),
                'errores' => [],
            ]);
        }

        $lre->update([
            'estado'             => LreEnvio::ESTADO_ERROR_VALIDACION,
            'errores_validacion' => $errores,
        ]);

        return response()->json([
            'lre'    => $lre->fresh(),
            'errores' => $errores,
        ], 422);
    }

    /** Registra el número de confirmación del portal Mi DT. */
    public function confirmarDt(Request $request, int $id): JsonResponse
    {
        $datos = $request->validate([
            'numero_confirmacion' => 'nullable|string|max:100',
        ]);

        $lre = LreEnvio::where('empresa_id', $request->user()->empresa_activa_id)
            ->find($id);

        if (! $lre) {
            return response()->json(['mensaje' => 'El LRE no existe o no pertenece a la empresa.'], 404);
        }

        try {
            $lre = $this->confirmarService->confirmar($lre, $datos['numero_confirmacion'] ?? null);
        } catch (RrhhException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return response()->json($lre);
    }

    /** Lista los registros LRE de la empresa con filtros opcionales por período. */
    public function index(Request $request): JsonResponse
    {
        $query = LreEnvio::where('empresa_id', $request->user()->empresa_activa_id)
            ->orderByDesc('anio')
            ->orderByDesc('mes');

        if ($request->filled('anio')) {
            $query->where('anio', (int) $request->query('anio'));
        }

        if ($request->filled('mes')) {
            $query->where('mes', (int) $request->query('mes'));
        }

        return response()->json($query->get());
    }

    /** Descarga el archivo .txt del LRE. */
    public function descargar(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $lre = LreEnvio::where('empresa_id', $request->user()->empresa_activa_id)
            ->find($id);

        if (! $lre) {
            return response()->json(['mensaje' => 'El LRE no existe o no pertenece a la empresa.'], 404);
        }

        try {
            $meta = $this->descargaService->prepararDescarga($lre);
        } catch (RrhhException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }

        return Storage::disk('sii_xml')->download($meta['ruta'], $meta['nombre_archivo']);
    }
}
