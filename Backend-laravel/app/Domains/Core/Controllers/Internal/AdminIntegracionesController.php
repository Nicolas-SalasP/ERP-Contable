<?php

namespace App\Domains\Core\Controllers\Internal;

use App\Domains\Integraciones\Http\Resources\IntegracionApiKeyResource;
use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use App\Domains\Sii\Models\SiiCertificadoEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Vista cross-empresa de "Integraciones" (SII + API keys) para el panel interno
 * de Tenri, en el mismo canal service-to-service (web.api.key) que ya usa
 * AdminEmpresasController -- sin sesion de usuario, confianza HMAC.
 */
class AdminIntegracionesController
{
    public function __construct(private readonly IntegracionApiKeyService $apiKeyService)
    {
    }

    public function resumen(): JsonResponse
    {
        $empresas = DB::table('empresas')->select('id', 'razon_social')->orderBy('id')->get();

        $apiKeysActivas = IntegracionApiKey::whereNull('revocada_at')
            ->selectRaw('empresa_id, COUNT(*) as total')
            ->groupBy('empresa_id')
            ->pluck('total', 'empresa_id');

        $certificados = SiiCertificadoEmpresa::activos()->get()->keyBy('empresa_id');

        $data = $empresas->map(function ($empresa) use ($apiKeysActivas, $certificados) {
            $cert = $certificados->get($empresa->id);

            return [
                'empresa_id' => (int) $empresa->id,
                'razon_social' => $empresa->razon_social,
                'sii' => $cert ? [
                    'estado' => $cert->estado,
                    'nivel_alerta' => $cert->nivelAlerta(),
                ] : null,
                'api_keys_activas' => (int) ($apiKeysActivas->get($empresa->id) ?? 0),
                'previred' => [
                    'integrado' => false,
                    'nota' => 'Sin monitoreo de conexión, solo generación de archivo manual.',
                ],
            ];
        });

        return response()->json(['empresas' => $data]);
    }

    public function sii(int $id): JsonResponse
    {
        if (!$this->empresaExiste($id)) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        $cert = SiiCertificadoEmpresa::where('empresa_id', $id)->where('estado', 'activo')->first();

        if (!$cert) {
            return response()->json(['certificado' => null]);
        }

        return response()->json([
            'certificado' => [
                'estado' => $cert->estado,
                'subject_common_name' => $cert->subject_common_name,
                'valido_desde' => $cert->valido_desde,
                'valido_hasta' => $cert->valido_hasta,
                'fingerprint_sha256' => $cert->fingerprint_sha256,
                'dias_para_vencer' => $cert->diasParaVencer(),
                'nivel_alerta' => $cert->nivelAlerta(),
            ],
        ]);
    }

    public function apiKeys(int $id): JsonResponse
    {
        if (!$this->empresaExiste($id)) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        $keys = IntegracionApiKey::where('empresa_id', $id)->orderByDesc('created_at')->get();

        return response()->json(['api_keys' => IntegracionApiKeyResource::collection($keys)]);
    }

    public function crearApiKey(Request $request, int $id): JsonResponse
    {
        if (!$this->empresaExiste($id)) {
            return response()->json(['message' => 'Empresa no encontrada.'], 404);
        }

        try {
            $datos = $request->validate([
                'nombre' => ['required', 'string', 'max:100'],
                'scopes' => ['required', 'array', 'min:1'],
                'scopes.*' => ['string', 'in:'.implode(',', IntegracionApiKey::SCOPES_VALIDOS)],
                'expira_at' => ['nullable', 'date', 'after:now'],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        }

        $emitida = $this->apiKeyService->emitir(
            $id,
            $datos['nombre'],
            $datos['scopes'],
            isset($datos['expira_at']) ? Carbon::parse($datos['expira_at']) : null,
        );

        return response()->json([
            'message' => 'Guarda este token ahora: no se puede volver a mostrar.',
            'key' => $emitida['key'],
            'token' => $emitida['token'],
        ], 201);
    }

    public function rotarApiKey(int $id, int $keyId): JsonResponse
    {
        $key = IntegracionApiKey::where('empresa_id', $id)->where('id', $keyId)->first();

        if (!$key) {
            return response()->json(['message' => 'API-key no encontrada.'], 404);
        }

        $rotada = $this->apiKeyService->rotar($key);

        return response()->json([
            'message' => 'Guarda este token ahora: no se puede volver a mostrar.',
            'key' => $rotada['key'],
            'token' => $rotada['token'],
        ]);
    }

    public function revocarApiKey(int $id, int $keyId): JsonResponse
    {
        $key = IntegracionApiKey::where('empresa_id', $id)->where('id', $keyId)->first();

        if (!$key) {
            return response()->json(['message' => 'API-key no encontrada.'], 404);
        }

        $this->apiKeyService->revocar($key);

        return response()->json(['message' => 'API-key revocada.']);
    }

    private function empresaExiste(int $id): bool
    {
        return DB::table('empresas')->where('id', $id)->exists();
    }
}
