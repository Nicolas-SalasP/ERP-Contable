<?php

namespace App\Domains\Soporte\Controllers;

use App\Domains\Core\Models\Empresa;
use App\Domains\Soporte\Services\SoporteClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SoporteController extends Controller
{
    public function __construct(private readonly SoporteClienteService $soporteCliente)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $resultado = $this->soporteCliente->listar($empresaId, $request->only(['status', 'category', 'page']));

        if ($resultado === null) {
            return response()->json(['message' => 'No se pudo conectar con el servicio de soporte.'], 503);
        }

        return response()->json($resultado);
    }

    public function store(Request $request): JsonResponse
    {
        $usuario   = $request->user();
        $empresaId = (int) $usuario->empresa_id;

        $validated = $request->validate([
            'subject'  => 'required|string|max:255',
            'category' => 'nullable|string|in:ERP,Web,Facturacion,Soporte',
            'priority' => 'nullable|string|in:baja,media,alta,critica',
            'message'  => 'required|string',
        ]);

        $empresa = Empresa::find($empresaId);

        $payload = [
            'empresa_id'     => $empresaId,
            'empresa_nombre' => $empresa?->nombre ?? 'Sin nombre',
            'origen_email'   => $usuario->email,
            'origen_nombre'  => $usuario->name,
            'subject'        => $validated['subject'],
            'category'       => $validated['category'] ?? 'ERP',
            'priority'       => $validated['priority'] ?? 'media',
            'message'        => $validated['message'],
        ];

        $resultado = $this->soporteCliente->crear($payload);

        if ($resultado === null) {
            return response()->json(['message' => 'No se pudo crear el ticket.'], 503);
        }

        return response()->json($resultado, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $resultado = $this->soporteCliente->ver($empresaId, $id);

        if ($resultado === null) {
            return response()->json(['message' => 'Ticket no encontrado.'], 404);
        }

        return response()->json($resultado);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $resultado = $this->soporteCliente->responder(
            (int) $usuario->empresa_id,
            $id,
            $validated['message'],
            $usuario->email,
            $usuario->name,
        );

        if ($resultado === null) {
            return response()->json(['message' => 'No se pudo enviar la respuesta.'], 503);
        }

        return response()->json($resultado, 201);
    }
}
