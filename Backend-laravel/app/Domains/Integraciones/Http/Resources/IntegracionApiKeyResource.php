<?php

namespace App\Domains\Integraciones\Http\Resources;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Allowlist explicita para listados de API-keys: token_hash ya esta en $hidden del
 * modelo, pero este Resource fija el contrato de forma explicita en vez de depender
 * de un wrap automatico del modelo (mismo criterio que ProductoIntegracionResource).
 *
 * @property IntegracionApiKey $resource
 */
class IntegracionApiKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $key = $this->resource;

        return [
            'id' => $key->id,
            'empresa_id' => $key->empresa_id,
            'nombre' => $key->nombre,
            'prefijo' => $key->prefijo,
            'scopes' => $key->scopes,
            'ultimo_uso_at' => $key->ultimo_uso_at?->toIso8601String(),
            'expira_at' => $key->expira_at?->toIso8601String(),
            'revocada_at' => $key->revocada_at?->toIso8601String(),
            'vigente' => $key->estaVigente(),
            'created_at' => $key->created_at?->toIso8601String(),
            'updated_at' => $key->updated_at?->toIso8601String(),
        ];
    }
}
