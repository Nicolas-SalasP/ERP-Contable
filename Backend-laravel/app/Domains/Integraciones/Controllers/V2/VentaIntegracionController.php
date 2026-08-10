<?php

namespace App\Domains\Integraciones\Controllers\V2;

use App\Domains\Integraciones\Models\IntegracionApiKey;
use App\Domains\Integraciones\Services\VentaIntegracionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Reservas de stock, confirmacion de venta y consulta de estado del DTE contra el ERP, para checkout externo (Tenri-Web-Page u otro tercero). Escritura requiere scope ventas:escribir, lectura requiere ventas:leer (ver Routes/ventas.php). */
class VentaIntegracionController
{
    public function __construct(private readonly VentaIntegracionService $servicio) {}

    public function reservar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
        ]);

        $reserva = $this->servicio->reservar($this->empresaId($request), $datos);

        return response()->json([
            'success' => true,
            'data' => $reserva,
        ], 201);
    }

    public function liberar(Request $request, int $id): JsonResponse
    {
        $this->servicio->liberar($this->empresaId($request), $id);

        return response()->json(['success' => true, 'message' => 'Reserva liberada.']);
    }

    public function confirmar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'reserva_id' => ['required', 'integer'],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'cliente' => ['nullable', 'array'],
            'cliente.rut' => ['nullable', 'string', 'max:20'],
            'cliente.nombre' => ['nullable', 'string', 'max:200'],
            'cliente.email' => ['nullable', 'email', 'max:150'],
            'items' => ['nullable', 'array'],
            'items.*.sku' => ['required_with:items', 'string', 'max:100'],
            'items.*.cantidad' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.numero_serie' => ['nullable', 'string', 'max:120'],
            'items.*.precio_unitario_neto' => ['nullable', 'numeric', 'min:0'],
            'items.*.monto_neto_linea' => ['nullable', 'numeric', 'min:0'],
            'despacho' => ['nullable', 'array'],
            'despacho.monto_neto' => ['nullable', 'numeric', 'min:0'],
        ]);

        $resultado = $this->servicio->confirmar(
            $this->empresaId($request),
            $datos,
            $request->header('Idempotency-Key')
        );

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ], 201);
    }

    /** GET /ventas/{facturaId}: estado actual del DTE (para polling del canal externo hasta que folio/pdf_url existan). */
    public function estado(Request $request, int $facturaId): JsonResponse
    {
        $resultado = $this->servicio->obtenerEstado($this->empresaId($request), $facturaId);

        return response()->json([
            'success' => true,
            'data' => $resultado,
        ]);
    }

    private function empresaId(Request $request): int
    {
        /** @var IntegracionApiKey $key */
        $key = $request->attributes->get('integracion_key');

        return (int) $key->empresa_id;
    }
}
