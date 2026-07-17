<?php

namespace App\Domains\Integraciones\Http\Middleware;

use App\Domains\Core\Models\User;
use App\Domains\Integraciones\Services\IntegracionApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica requests de terceros (Tenri-Web-Page, WordPress, etc.) por API-key, NO por HMAC
 * (eso es solo para Web) ni por sesion Sanctum (no hay usuario logueado). No llama a
 * Auth::login(): deja la key resuelta en el request y el resto del pipeline (servicios,
 * controllers) filtra `empresa_id` explicitamente por ella. EmpresaScope se apaga sin
 * auth()->check() -> NUNCA usarlo como unica defensa multitenant en este path.
 */
class AutenticarApiKey
{
    public function __construct(private readonly IntegracionApiKeyService $servicio) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extraerToken($request);

        if ($token === null) {
            return $this->noAutorizado('Falta la API-key.');
        }

        $key = $this->servicio->resolverDesdeToken($token);

        if ($key === null) {
            return $this->noAutorizado('API-key invalida.');
        }

        if (! $key->estaVigente()) {
            return $this->noAutorizado('API-key revocada o expirada.');
        }

        if (! $this->empresaTieneModuloHabilitado((int) $key->empresa_id)) {
            return $this->noAutorizado('La empresa no tiene el modulo de integraciones habilitado en su plan.');
        }

        $this->servicio->registrarUso($key);

        $request->attributes->set('integracion_key', $key);

        return $next($request);
    }

    private function extraerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        $apiKeyHeader = $request->header('X-Api-Key');

        return is_string($apiKeyHeader) && $apiKeyHeader !== '' ? $apiKeyHeader : null;
    }

    /**
     * Runtime: la empresa duena de la key debe seguir teniendo 'integraciones.api' en el
     * module_keys de al menos un usuario. A diferencia del RBAC normal, este modulo nunca
     * puede estar en MODULOS_SIEMPRE_DISPONIBLES (es opt-in premium por diseno) -> no hace
     * falta esa excepcion aca.
     *
     * whereJsonContains + exists() empuja el chequeo a SQL (compatible SQLite y MySQL): antes
     * traia TODAS las filas de usuarios de la empresa a PHP solo para revisar un booleano, en
     * cada request de una API pensada para alto volumen (sync de inventario).
     */
    private function empresaTieneModuloHabilitado(int $empresaId): bool
    {
        return User::query()
            ->where('empresa_id', $empresaId)
            ->whereJsonContains('module_keys', 'integraciones.api')
            ->exists();
    }

    private function noAutorizado(string $mensaje): Response
    {
        return response()->json(['success' => false, 'message' => $mensaje], 401);
    }
}
