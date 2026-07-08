<?php

namespace App\Domains\Contabilidad\Controllers;

use App\Domains\Contabilidad\Exceptions\DjException;
use App\Domains\Contabilidad\Models\DjEnvio;
use App\Domains\Contabilidad\Services\Dj\DjEngine;
use App\Domains\Contabilidad\Services\Dj\Dj1926Service;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class Dj1926Controller extends Controller
{
    public function __construct(
        private readonly DjEngine      $engine,
        private readonly Dj1926Service $dj1926,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_activa_id;

        $envios = DjEnvio::where('empresa_id', $empresaId)
            ->porCodigo('1926')
            ->orderByDesc('anio')
            ->get(['id', 'anio', 'estado', 'cantidad_registros', 'folio_presentacion', 'presentado_at', 'created_at']);

        return response()->json(['data' => $envios]);
    }

    public function generar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'anio' => 'required|integer|min:2020|max:2099',
        ]);

        try {
            $envio = $this->engine->generar(
                $this->dj1926,
                $request->user()->empresa_activa_id,
                (int) $data['anio'],
            );

            return response()->json([
                'mensaje' => 'DJ 1926 generada correctamente.',
                'data'    => $envio,
            ], 201);
        } catch (DjException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function validar(Request $request, DjEnvio $djEnvio): JsonResponse
    {
        abort_unless((int) $djEnvio->empresa_id === (int) $request->user()->empresa_activa_id, 403);

        try {
            $errores = $this->engine->validar($this->dj1926, $djEnvio);

            return response()->json([
                'valido'  => empty($errores),
                'errores' => $errores,
            ]);
        } catch (DjException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function descargar(Request $request, DjEnvio $djEnvio)
    {
        abort_unless((int) $djEnvio->empresa_id === (int) $request->user()->empresa_activa_id, 403);

        if (! $djEnvio->archivo_path) {
            return response()->json(['message' => 'No hay archivo generado.'], 422);
        }

        $disco = config('sii.storage.disk', 'sii_xml');

        if (! Storage::disk($disco)->exists($djEnvio->archivo_path)) {
            return response()->json(['message' => 'Archivo no encontrado en disco.'], 404);
        }

        return Storage::disk($disco)->download($djEnvio->archivo_path, "DJ1926_{$djEnvio->anio}.txt");
    }

    public function confirmarPresentacion(Request $request, DjEnvio $djEnvio): JsonResponse
    {
        abort_unless((int) $djEnvio->empresa_id === (int) $request->user()->empresa_activa_id, 403);

        $data = $request->validate([
            'folio_presentacion' => 'nullable|string|max:50',
        ]);

        if (! in_array($djEnvio->estado, [DjEnvio::ESTADO_VALIDADO, DjEnvio::ESTADO_GENERADO])) {
            return response()->json(['message' => 'El envío debe estar en estado VALIDADO o GENERADO para confirmar.'], 422);
        }

        $djEnvio->update([
            'estado'             => DjEnvio::ESTADO_PRESENTADO,
            'folio_presentacion' => $data['folio_presentacion'] ?? null,
            'presentado_at'      => now(),
        ]);

        return response()->json([
            'mensaje' => 'DJ 1926 marcada como presentada.',
            'data'    => $djEnvio->fresh(),
        ]);
    }
}
