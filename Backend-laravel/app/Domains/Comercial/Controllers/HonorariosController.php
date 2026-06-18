<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Domains\Comercial\Models\HonorarioRecibido;
use App\Domains\Comercial\Services\Honorarios\RegistrarHonorarioService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HonorariosController extends Controller
{
    public function __construct(
        private readonly RegistrarHonorarioService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = HonorarioRecibido::where('empresa_id', $request->user()->empresa_id)
            ->orderByDesc('fecha');

        if ($request->filled('mes') && $request->filled('anio')) {
            $query->whereMonth('fecha', (int) $request->mes)
                  ->whereYear('fecha', (int) $request->anio);
        } elseif ($request->filled('anio')) {
            $query->whereYear('fecha', (int) $request->anio);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rut_prestador'    => 'required|string|max:12',
            'nombre_prestador' => 'required|string|max:255',
            'numero_boleta'    => 'nullable|string|max:20',
            'fecha'            => 'required|date',
            'monto_bruto'      => 'required|integer|min:1',
            'proveedor_id'     => 'nullable|integer|exists:proveedores,id',
        ]);

        try {
            $honorario = $this->service->registrar($request->user()->empresa_id, $datos);
            return response()->json(['data' => $honorario], 201);
        } catch (ComercialException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, HonorarioRecibido $honorariosRecibido): JsonResponse
    {
        abort_unless((int) $honorariosRecibido->empresa_id === (int) $request->user()->empresa_id, 403);
        $honorariosRecibido->delete();
        return response()->json(['mensaje' => 'Honorario eliminado.']);
    }
}
