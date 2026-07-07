<?php

namespace App\Domains\Inventario\Controllers;

use App\Domains\Inventario\Exceptions\InventarioException;
use App\Support\MensajeErrorGenerico;

use App\Domains\Inventario\Controllers\Concerns\RespondeInventario;
use App\Domains\Inventario\Services\InventarioAlertaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Concern separado de Reposición: distinto servicio (InventarioAlertaService) y distinta responsabilidad. */
class AlertaController
{
    use RespondeInventario;

    public function __construct(
        protected InventarioAlertaService $alertaService,
    ) {
    }

    public function alertas(Request $request): JsonResponse
    {
        try {
            $filtros = $request->validate([
                'tipo' => ['nullable', 'string', 'max:80'],
                'severidad' => ['nullable', 'in:baja,media,alta,critica'],
                'producto_id' => ['nullable', 'integer'],
                'bodega_id' => ['nullable', 'integer'],
                'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $resultado = $this->alertaService->listar($request->user(), $filtros);

            return response()->json([
                'success' => true,
                'data' => $resultado['data'],
                'resumen' => $resultado['resumen'],
                'metadata' => $resultado['metadata'],
            ]);
        } catch (ValidationException $e) {
            return $this->respuestaValidacion($e);
        } catch (InventarioException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->respuestaError($e);
        }
    }
}
