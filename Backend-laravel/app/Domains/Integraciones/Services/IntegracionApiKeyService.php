<?php

namespace App\Domains\Integraciones\Services;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Support\TokenApiKey;
use Illuminate\Support\Carbon;

class IntegracionApiKeyService
{
    /**
     * Emite una key nueva. El token en claro solo se devuelve aca -> el llamador
     * (controller) debe mostrarlo al usuario una unica vez; en BD nunca se guarda.
     *
     * @param string[] $scopes
     * @return array{key: IntegracionApiKey, token: string}
     */
    public function emitir(int $empresaId, string $nombre, array $scopes, ?Carbon $expiraAt = null): array
    {
        $generado = TokenApiKey::generar();

        $key = IntegracionApiKey::create([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'prefijo' => $generado['prefijo'],
            'token_hash' => $generado['hash'],
            'scopes' => $scopes,
            'expira_at' => $expiraAt,
        ]);

        return ['key' => $key, 'token' => $generado['token']];
    }

    /**
     * Rota el secreto de una key existente (mismo prefijo/scopes/empresa), invalidando el
     * token anterior. Util cuando se sospecha que el secreto se filtro sin querer revocar
     * el acceso por completo (evita reconfigurar el consumidor con una key nueva).
     *
     * @return array{key: IntegracionApiKey, token: string}
     */
    public function rotar(IntegracionApiKey $key): array
    {
        $generado = TokenApiKey::generar();

        $key->update([
            'prefijo' => $generado['prefijo'],
            'token_hash' => $generado['hash'],
        ]);

        return ['key' => $key->refresh(), 'token' => $generado['token']];
    }

    public function revocar(IntegracionApiKey $key): void
    {
        if ($key->revocada_at === null) {
            $key->update(['revocada_at' => now()]);
        }
    }

    /** Busca la key por el token completo presentado en el request; null si no matchea o esta invalida. */
    public function resolverDesdeToken(string $token): ?IntegracionApiKey
    {
        $partes = TokenApiKey::parsear($token);

        if ($partes === null) {
            return null;
        }

        $key = IntegracionApiKey::where('prefijo', $partes['prefijo'])->first();

        if (! $key || ! hash_equals($key->token_hash, TokenApiKey::hash($partes['secreto']))) {
            return null;
        }

        return $key;
    }

    public function registrarUso(IntegracionApiKey $key): void
    {
        $key->update(['ultimo_uso_at' => now()]);
    }
}
