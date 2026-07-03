<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Models\OrdenCompra;
use App\Domains\Comercial\Services\OrdenCompraService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrdenCompraController extends Controller
{
    public function __construct(
        private readonly OrdenCompraService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_activa_id;
        $filtros = $request->only(['estado', 'proveedor']);
        $paginado = $this->service->listar($empresaId, $filtros);
        return response()->json($paginado);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
            'numero_oc' => 'nullable|string|max:30',
            'fecha_emision' => 'required|date',
            'fecha_entrega_esperada' => 'nullable|date',
            'estado' => 'nullable|in:BORRADOR,ENVIADA',
            'moneda' => 'nullable|string|max:10',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.producto_descripcion' => 'required|string|max:255',
            'detalles.*.codigo_producto' => 'nullable|string|max:100',
            'detalles.*.cantidad' => 'required|numeric|min:0.001',
            'detalles.*.unidad' => 'nullable|string|max:20',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.subtotal' => 'required|numeric|min:0',
        ]);

        $empresaId = (int) $request->user()->empresa_activa_id;
        $oc = $this->service->crear($empresaId, $datos);
        return response()->json(['data' => $oc], 201);
    }

    public function show(Request $request, OrdenCompra $ordenCompra): JsonResponse
    {
        abort_unless((int) $ordenCompra->empresa_id === (int) $request->user()->empresa_activa_id, 404);
        $ordenCompra->load('detalles', 'proveedor');
        return response()->json(['data' => $ordenCompra]);
    }

    public function update(Request $request, OrdenCompra $ordenCompra): JsonResponse
    {
        abort_unless((int) $ordenCompra->empresa_id === (int) $request->user()->empresa_activa_id, 404);

        $datos = $request->validate([
            'proveedor_id' => 'nullable|integer|exists:proveedores,id',
            'fecha_emision' => 'nullable|date',
            'fecha_entrega_esperada' => 'nullable|date',
            // 'estado' NO es editable via este endpoint -- solo cambia por
            // anular()/recibirParcial(), que llevan sus propios guards de
            // transicion. Aceptarlo aqui permitia saltar RECIBIDA_TOTAL sin
            // pasar por el conteo real de cantidades recibidas por detalle.
            'moneda' => 'nullable|string|max:10',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'detalles' => 'nullable|array',
            'detalles.*.producto_descripcion' => 'required_with:detalles|string|max:255',
            'detalles.*.codigo_producto' => 'nullable|string|max:100',
            'detalles.*.cantidad' => 'required_with:detalles|numeric|min:0.001',
            'detalles.*.unidad' => 'nullable|string|max:20',
            'detalles.*.precio_unitario' => 'required_with:detalles|numeric|min:0',
            'detalles.*.subtotal' => 'required_with:detalles|numeric|min:0',
        ]);

        $oc = $this->service->actualizar($ordenCompra, $datos);
        return response()->json(['data' => $oc]);
    }

    public function destroy(Request $request, OrdenCompra $ordenCompra): JsonResponse
    {
        abort_unless((int) $ordenCompra->empresa_id === (int) $request->user()->empresa_activa_id, 404);
        $this->service->anular($ordenCompra);
        return response()->json(['mensaje' => 'Orden de compra anulada.']);
    }

    public function recibirMercaderia(Request $request, OrdenCompra $ordenCompra): JsonResponse
    {
        abort_unless((int) $ordenCompra->empresa_id === (int) $request->user()->empresa_activa_id, 404);

        $datos = $request->validate([
            'recepciones' => 'required|array|min:1',
            'recepciones.*.detalle_id' => 'required|integer',
            'recepciones.*.cantidad_recibida' => 'required|numeric|min:0.001',
        ]);

        $oc = $this->service->recibirParcial($ordenCompra, $datos['recepciones']);
        return response()->json(['data' => $oc]);
    }
}
