<?php

namespace App\Domains\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Services\EmpresaService;
use Illuminate\Support\Facades\DB;
use Exception;

class EmpresaController extends Controller
{
    protected $empresaService;

    public function __construct(EmpresaService $empresaService)
    {
        $this->empresaService = $empresaService;
    }

    public function perfil(Request $request)
    {
        $empresa = Empresa::with(['centrosCosto', 'cuentasBancarias'])->find($request->user()->empresa_id);

        if (!$empresa) {
            return response()->json(['success' => false, 'message' => 'Empresa no encontrada'], 404);
        }

        $datos = $empresa->toArray();
        $datos['bancos'] = $empresa->cuentasBancarias;
        $datos['centros_costo'] = $empresa->centrosCosto;

        return response()->json(['success' => true, 'data' => $datos]);
    }

    public function catalogoBancos()
    {
        $bancos = DB::table('catalogo_bancos')->orderBy('nombre', 'asc')->get();
        return response()->json(['success' => true, 'data' => $bancos]);
    }

    public function actualizarPerfil(Request $request)
    {
        try {
            $datos = $request->except('logo');
            $empresa = $this->empresaService->actualizarDatos($request->user()->empresa_id, $datos);

            if ($request->hasFile('logo')) {
                $this->empresaService->actualizarLogo($request->user()->empresa_id, $request->file('logo'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado.',
                'data' => $empresa->fresh()
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function subirLogo(Request $request)
    {
        try {
            $request->validate(['logo' => 'required|image|mimes:jpeg,png,jpg|max:2048']);

            $path = $this->empresaService->actualizarLogo($request->user()->empresa_id, $request->file('logo'));

            return response()->json(['success' => true, 'logo_url' => $path]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function agregarBanco(Request $request)
    {
        try {
            $cuenta = $this->empresaService->agregarBanco($request->user()->empresa_id, $request->all());
            return response()->json(['success' => true, 'data' => $cuenta]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function eliminarBanco(Request $request, $id)
    {
        try {
            $this->empresaService->eliminarBanco($request->user()->empresa_id, $id);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function actualizarBanco(Request $request, $id)
    {
        try {
            $cuenta = $this->empresaService->actualizarBanco($request->user()->empresa_id, $id, $request->all());
            return response()->json(['success' => true, 'data' => $cuenta]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function listarCentros(Request $request)
    {
        $centros = \App\Domains\Contabilidad\Models\CentroCosto::where('empresa_id', $request->user()->empresa_id)
            ->where('activo', true)
            ->get();
        $centrosFormateados = $centros->map(function ($c) {
            return [
                'value' => $c->id,
                'label' => $c->codigo . ' - ' . $c->nombre
            ];
        });

        return response()->json(['success' => true, 'data' => $centrosFormateados]);
    }

    public function agregarCentro(Request $request)
    {
        try {
            $centro = $this->empresaService->agregarCentroCosto($request->user()->empresa_id, $request->all());
            return response()->json(['success' => true, 'data' => $centro]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function eliminarCentro(Request $request, $id)
    {
        try {
            $this->empresaService->eliminarCentroCosto($request->user()->empresa_id, $id);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function actualizarCentro(Request $request, $id)
    {
        try {
            $centro = $this->empresaService->actualizarCentroCosto($request->user()->empresa_id, $id, $request->all());
            return response()->json(['success' => true, 'data' => $centro]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
}