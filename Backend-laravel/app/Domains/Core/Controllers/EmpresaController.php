<?php

namespace App\Domains\Core\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Core\Models\Empresa;
use App\Domains\Core\Services\EmpresaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\MensajeErrorGenerico;
use App\Domains\Core\Support\ModuloPermisos;
use App\Domains\Sii\Support\RutHelper;
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
        $empresa = Empresa::with(['centrosCosto', 'cuentasBancarias'])->find($request->user()->empresa_activa_id);

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
            // Whitelist explicita de campos editables; antes se usaba $request->except(...), que permitia mass-assignment de 'activa'/'tasa_impuesto'.
            $datos = $request->validate([
                'razon_social' => 'nullable|string|max:150',
                'direccion' => 'nullable|string|max:255',
                'telefono' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:100',
                'color_primario' => 'nullable|string|max:20',
                'regimen_tributario' => 'nullable|in:14_D3,14_D8,14_A',
                'ppm_pct' => 'nullable|numeric|min:0|max:10',
            ]);

            $empresa = $this->empresaService->actualizarDatos($request->user()->empresa_activa_id, $datos);

            if ($request->hasFile('logo')) {
                $this->empresaService->actualizarLogo($request->user()->empresa_activa_id, $request->file('logo'));
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
            Log::error('EmpresaController: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno del servidor. Intentelo mas tarde.'], 500);
        }
    }

    public function subirLogo(Request $request)
    {
        try {
            $request->validate(['logo' => 'required|image|mimes:jpeg,png,jpg|max:2048']);

            $path = $this->empresaService->actualizarLogo($request->user()->empresa_activa_id, $request->file('logo'));

            return response()->json(['success' => true, 'logo_url' => $path]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('EmpresaController (logo): ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'No se pudo procesar la solicitud.'], 500);
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

            $cuenta = $this->empresaService->agregarBanco($request->user()->empresa_activa_id, $datos);
            return response()->json(['success' => true, 'data' => $cuenta], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('EmpresaController: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno del servidor. Intentelo mas tarde.'], 500);
        }
    }

    public function eliminarBanco(Request $request, $id)
    {
        try {
            $this->empresaService->eliminarBanco($request->user()->empresa_activa_id, $id);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('EmpresaController: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno del servidor. Intentelo mas tarde.'], 500);
        }
    }

    public function actualizarBanco(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'banco' => 'sometimes|required|string|max:100',
                'tipo_cuenta' => 'sometimes|required|string|max:50',
                'numero_cuenta' => 'sometimes|required|string|max:50',
                'cuenta_contable' => 'nullable|string|max:20',
            ]);

            $cuenta = $this->empresaService->actualizarBanco($request->user()->empresa_activa_id, $id, $datos);
            return response()->json(['success' => true, 'data' => $cuenta]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('EmpresaController: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno del servidor. Intentelo mas tarde.'], 500);
        }
    }

    public function listarCentros(Request $request)
    {
        $centros = \App\Domains\Contabilidad\Models\CentroCosto::where('empresa_id', $request->user()->empresa_activa_id)
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

            $centro = $this->empresaService->agregarCentroCosto($request->user()->empresa_activa_id, $datos);
            return response()->json(['success' => true, 'data' => $centro]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function eliminarCentro(Request $request, $id)
    {
        try {
            $this->empresaService->eliminarCentroCosto($request->user()->empresa_activa_id, $id);
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('EmpresaController: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error interno del servidor. Intentelo mas tarde.'], 500);
        }
    }

    public function actualizarCentro(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'codigo' => 'sometimes|required|string|max:20',
                'nombre' => 'sometimes|required|string|max:100',
                'activo' => 'sometimes|boolean',
            ]);

            $centro = $this->empresaService->actualizarCentroCosto($request->user()->empresa_activa_id, $id, $datos);
            return response()->json(['success' => true, 'data' => $centro]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => MensajeErrorGenerico::desde($e)], 400);
        }
    }

    public function verificarRut(Request $request)
    {
        $rut = trim($request->query('rut', ''));

        if (!$rut) {
            return response()->json(['existe' => false, 'valido' => false]);
        }

        // Validacion autoritativa del digito verificador en el servidor.
        $valido = RutHelper::validar($rut);

        // Solo tiene sentido consultar existencia si el RUT es valido.
        $existe = $valido ? Empresa::where('rut', $rut)->exists() : false;

        return response()->json(['existe' => $existe, 'valido' => $valido]);
    }

    public function onboarding(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'No autenticado.'], 401);
        }

        if ($user->empresa_id) {
            return response()->json(['success' => false, 'message' => 'Este usuario ya tiene una empresa asignada.'], 422);
        }

        // Se normaliza el RUT antes de validar para que la unicidad compare contra el formato real almacenado en BD (sin puntos).
        $rutNormalizado = null;
        try {
            $rutNormalizado = RutHelper::normalizar((string) $request->empresa_rut);
        } catch (\Exception $e) {
            // La validación de formato (RutHelper::validar) se encarga del error.
        }

        $request->validate([
            'empresa_rut'          => [
                'required', 'string', 'max:20',
                function ($attribute, $value, $fail) {
                    if (!RutHelper::validar($value)) {
                        $fail('El RUT ingresado no es valido: revise el digito verificador.');
                    }
                },
            ],
            'empresa_razon_social' => ['required', 'string', 'max:150'],
            'giro'                 => ['nullable', 'string', 'max:80'],
            'direccion'            => ['nullable', 'string', 'max:255'],
            'telefono'             => ['nullable', 'string', 'max:50'],
            'regimen_tributario'   => ['nullable', 'in:14_D3,14_D8,14_A'],
        ]);

        // Unicidad server-side sobre el RUT normalizado (sin puntos).
        $rutParaGuardar = $rutNormalizado ?? $request->empresa_rut;
        if (Empresa::where('rut', $rutParaGuardar)->exists()) {
            throw ValidationException::withMessages([
                'empresa_rut' => ['Este RUT ya está registrado en el sistema.'],
            ]);
        }

        return DB::transaction(function () use ($request, $user, $rutParaGuardar) {
            $empresa = Empresa::create([
                'rut'                => $rutParaGuardar,
                'razon_social'       => $request->empresa_razon_social,
                'giro_emisor'        => $request->giro,
                'direccion'          => $request->direccion,
                'telefono'           => $request->telefono,
                'regimen_tributario' => $request->regimen_tributario ?? '14_D3',
            ]);

            // User::booted() solo copia empresa_id -> empresa_activa_id en "creating"; este usuario ya existe, así que hay que setear ambos o EmpresaScope lo deja sin ver la empresa recién creada.
            $user->empresa_id = $empresa->id;
            $user->empresa_activa_id = $empresa->id;
            $user->save();

            // Sin esto, empresa_user (tabla que EmpresaCambioController::misEmpresas/cambiar usa
            // para listar y autorizar el cambio de empresa activa) nunca ve esta empresa: el
            // onboarding queda con acceso "real" vía empresa_id pero invisible para multiempresa.
            DB::table('empresa_user')->insertOrIgnore([
                'user_id'    => $user->id,
                'empresa_id' => $empresa->id,
                'rol_id'     => $user->rol_id,
                'created_at' => now(),
            ]);

            $user->currentAccessToken()->delete();
            $token = $user->createToken('react-spa-token')->plainTextToken;

            $user->load(['empresa', 'rol']);

            $permisos = $user->rol->permisos ?? [];
            if ($user->rol && $user->rol->jerarquia >= 100) {
                $permisos = ModuloPermisos::todosLosPermisos();
            }

            return response()->json([
                'success' => true,
                'token'   => $token,
                'user'    => [
                    'id'         => $user->id,
                    'nombre'     => $user->nombre,
                    'email'      => $user->email,
                    'empresa_id' => $user->empresa_id,
                    'rol_id'     => $user->rol_id,
                    'plan_slug'  => $user->plan_slug,
                    'permisos'   => $permisos,
                ],
            ]);
        });
    }
}
