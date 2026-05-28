<?php

namespace App\Domains\Sii\Http\Controllers;

use App\Domains\Sii\Exceptions\CafInvalidoException;
use App\Domains\Sii\Http\Requests\RevocarCafRequest;
use App\Domains\Sii\Http\Requests\SubirCafRequest;
use App\Domains\Sii\Models\SiiCaf;
use App\Domains\Sii\Models\SiiDteEmitido;
use App\Domains\Sii\Services\Caf\CafService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LogicException;

class SiiCafController extends Controller
{
    public function __construct(private readonly CafService $cafs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $tipoDte   = $request->query('tipo_dte');

        $query = SiiCaf::query()->porEmpresa($empresaId)->orderBy('id', 'desc');

        if ($tipoDte !== null && $tipoDte !== '') {
            $query->where('tipo_dte', (int) $tipoDte);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function saldos(Request $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        $tipos = SiiCaf::query()
            ->where('empresa_id', $empresaId)
            ->distinct()
            ->orderBy('tipo_dte')
            ->pluck('tipo_dte');

        $data = [];
        foreach ($tipos as $tipo) {
            $saldo = $this->cafs->obtenerSaldoPorTipo($empresaId, (int) $tipo);
            $data[(string) $tipo] = array_merge(
                ['tipo_dte' => (int) $tipo, 'nombre' => SiiDteEmitido::nombreTipo((int) $tipo)],
                $saldo
            );
        }

        return response()->json(['data' => $data]);
    }

    public function store(SubirCafRequest $request): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;
        $contenido = (string) file_get_contents($request->file('archivo')->getRealPath());

        try {
            $caf = $this->cafs->cargar($empresaId, $contenido);
        } catch (CafInvalidoException $e) {
            return response()->json([
                'mensaje'    => $e->getMessage(),
                'error_code' => $e->motivo,
            ], 422);
        }

        return response()->json($caf, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        // findOrFail dentro de scope porEmpresa = 404 si pertenece a otra empresa (IDOR-safe).
        $caf = SiiCaf::query()->porEmpresa($empresaId)->findOrFail($id);

        return response()->json($caf);
    }

    public function destroy(int $id, RevocarCafRequest $request): Response|JsonResponse
    {
        $empresaId = (int) $request->user()->empresa_id;

        $caf = SiiCaf::query()->porEmpresa($empresaId)->findOrFail($id);

        try {
            $this->cafs->revocar($caf, $request->validated('motivo'));
        } catch (LogicException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 409);
        }

        return response()->noContent();
    }
}
