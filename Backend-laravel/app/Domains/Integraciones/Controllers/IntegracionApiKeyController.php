<?php

namespace App\Domains\Integraciones\Controllers;

use App\Domains\Core\Models\User;
use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * CRUD de administracion de API-keys del dominio Integraciones, dentro del ERP (Sanctum + permiso).
 * El token en claro solo se devuelve en la respuesta de store()/rotar(): nunca se persiste ni se
 * puede recuperar despues -> si el cliente lo pierde, la unica opcion es rotar.
 */
class IntegracionApiKeyController
{
    public function __construct(private readonly IntegracionApiKeyService $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $paginador = IntegracionApiKey::where('empresa_id', $this->empresaActivaId($request))
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('limit', 15));

        return response()->json([
            'success' => true,
            'data' => $paginador->items(),
            'pagination' => [
                'total' => $paginador->total(),
                'total_pages' => $paginador->lastPage(),
                'page' => $paginador->currentPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:inventario:leer,inventario:escribir'],
            'expira_at' => ['nullable', 'date', 'after:now'],
        ]);

        $emitida = $this->servicio->emitir(
            $this->empresaActivaId($request),
            $datos['nombre'],
            $datos['scopes'],
            isset($datos['expira_at']) ? Carbon::parse($datos['expira_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Guarda este token ahora: no se puede volver a mostrar.',
            'data' => [
                'key' => $emitida['key'],
                'token' => $emitida['token'],
            ],
        ], 201);
    }

    public function rotar(Request $request, int $id): JsonResponse
    {
        $key = $this->buscarDeLaEmpresa($request, $id);

        if ($key === null) {
            return response()->json(['success' => false, 'message' => 'API-key no encontrada.'], 404);
        }

        $rotada = $this->servicio->rotar($key);

        return response()->json([
            'success' => true,
            'message' => 'Guarda este token ahora: no se puede volver a mostrar.',
            'data' => [
                'key' => $rotada['key'],
                'token' => $rotada['token'],
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $key = $this->buscarDeLaEmpresa($request, $id);

        if ($key === null) {
            return response()->json(['success' => false, 'message' => 'API-key no encontrada.'], 404);
        }

        $this->servicio->revocar($key);

        return response()->json(['success' => true, 'message' => 'API-key revocada.']);
    }

    private function buscarDeLaEmpresa(Request $request, int $id): ?IntegracionApiKey
    {
        return IntegracionApiKey::where('empresa_id', $this->empresaActivaId($request))
            ->where('id', $id)
            ->first();
    }

    private function empresaActivaId(Request $request): int
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return (int) $usuario->empresa_activa_id;
    }
}
