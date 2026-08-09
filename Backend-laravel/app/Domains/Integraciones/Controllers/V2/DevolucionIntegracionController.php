<?php

namespace App\Domains\Integraciones\Controllers\V2;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Services\DevolucionIntegracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Devoluciones/RMA contra el ERP real, para un tercero (Tenri-Web-Page u otro). Requiere scope devoluciones:escribir (ver Routes/devoluciones.php). */
class DevolucionIntegracionController
{
    public function __construct(private readonly DevolucionIntegracionService $servicio) {}

    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'factura_id' => ['required', 'integer'],
            'tipo' => ['nullable', 'string', 'in:retracto,garantia'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:100'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.numero_serie' => ['nullable', 'string', 'max:120'],
            'items.*.motivo' => ['nullable', 'string', 'max:120'],
            'incluir_despacho' => ['nullable', 'boolean'],
        ]);

        $resultado = $this->servicio->crear(
            $this->empresaId($request),
            $datos,
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ], 201);
    }

    private function empresaId(Request $request): int
    {
        /** @var IntegracionApiKey $key */
        $key = $request->attributes->get('integracion_key');

        return (int) $key->empresa_id;
    }
}
