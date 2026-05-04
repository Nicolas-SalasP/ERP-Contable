<?php

namespace App\Domains\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Services\EmpresaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            $request->validate([
                'razon_social' => 'nullable|string|max:150',
                'direccion' => 'nullable|string|max:255',
                'telefono' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:100',
            ]);

            $datos = $request->except(['logo', 'rut', 'empresa_id', 'id']);

            $empresa = $this->empresaService->actualizarDatos($request->user()->empresa_id, $datos);

            if ($request->hasFile('logo')) {
                $this->empresaService->actualizarLogo($request->user()->empresa_id, $request->file('logo'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado.',
                'data' => $empresa->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
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

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function agregarBanco(Request $request)
    {
        try {
            $datos = $request->validate([
                'banco' => 'required|string|max:100',
                'tipo_cuenta' => 'required|string|max:50',
                'numero_cuenta' => 'required|string|max:50',
                'titular' => 'required|string|max:150',
                'rut_titular' => 'required|string|max:20',
                'email_notificacion' => 'nullable|email|max:100'
            ]);

            $cuenta = $this->empresaService->agregarBanco($request->user()->empresa_id, $datos);
            return response()->json(['success' => true, 'data' => $cuenta]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
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
            $datos = $request->validate([
                'codigo' => 'required|string|max:20',
                'nombre' => 'required|string|max:100'
            ]);

            $centro = $this->empresaService->agregarCentroCosto($request->user()->empresa_id, $datos);
            return response()->json(['success' => true, 'data' => $centro]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
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