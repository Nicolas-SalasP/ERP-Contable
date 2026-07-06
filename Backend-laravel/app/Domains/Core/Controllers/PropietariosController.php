<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Models\Propietario;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropietariosController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $propietarios = Propietario::where('empresa_id', $request->user()->empresa_activa_id)
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $propietarios]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'rut'                      => 'required|string|max:12',
            'nombre'                   => 'required|string|max:255',
            'porcentaje_participacion' => 'required|numeric|min:0.01|max:100',
        ]);

        $empresaId = $request->user()->empresa_activa_id;

        // Verificar unicidad de RUT por empresa
        $existe = Propietario::where('empresa_id', $empresaId)
            ->where('rut', $datos['rut'])
            ->exists();

        if ($existe) {
            return response()->json(['mensaje' => 'Ya existe un propietario con ese RUT en la empresa.'], 422);
        }

        $propietario = Propietario::create(array_merge($datos, ['empresa_id' => $empresaId]));

        return response()->json(['data' => $propietario], 201);
    }

    public function update(Request $request, Propietario $propietario): JsonResponse
    {
        abort_unless((int) $propietario->empresa_id === (int) $request->user()->empresa_activa_id, 403);

        $datos = $request->validate([
            'nombre'                   => 'sometimes|string|max:255',
            'porcentaje_participacion' => 'sometimes|numeric|min:0.01|max:100',
        ]);

        $propietario->update($datos);

        return response()->json(['data' => $propietario->fresh()]);
    }

    public function destroy(Request $request, Propietario $propietario): JsonResponse
    {
        abort_unless((int) $propietario->empresa_id === (int) $request->user()->empresa_activa_id, 403);
        $propietario->delete();
        return response()->json(['mensaje' => 'Propietario eliminado.']);
    }
}
