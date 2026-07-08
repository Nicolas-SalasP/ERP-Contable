<?php

namespace App\Domains\Comercial\Controllers;

use App\Domains\Comercial\Exceptions\ComercialException;
use App\Support\MensajeErrorGenerico;

use App\Http\Controllers\Controller;
use App\Domains\Comercial\Services\AnticipoClienteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Exception;

/**
 * @tags Anticipos de clientes
 */
class AnticipoClienteController extends Controller
{
    protected $service;

    public function __construct(AnticipoClienteService $service)
    {
        $this->service = $service;
    }

    public function store(Request $request)
    {
        try {
            $datos = $request->validate([
                'cliente_id' => 'required|integer',
                'monto' => 'required|numeric|min:1',
                'fecha' => 'nullable|date',
                'referencia' => 'nullable|string|max:255',
            ]);

            $anticipo = $this->service->registrar(
                $request->user()->empresa_activa_id,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Anticipo registrado exitosamente.',
                'data' => $anticipo
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors()
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
            ], 400);
        }
    }

    public function aplicar(Request $request, $id)
    {
        try {
            $datos = $request->validate([
                'factura_id' => 'required|integer',
                'monto_a_aplicar' => 'required|numeric|min:1',
            ]);

            $anticipo = $this->service->aplicarAFactura(
                $request->user()->empresa_activa_id,
                (int) $id,
                (int) $datos['factura_id'],
                (float) $datos['monto_a_aplicar']
            );

            return response()->json([
                'success' => true,
                'message' => 'Anticipo aplicado correctamente.',
                'data' => $anticipo
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e),
                'errors' => $e->errors()
            ], 422);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            $status = $e->getCode() === 404 ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => MensajeErrorGenerico::desde($e)
            ], $status);
        }
    }

    public function index(Request $request)
    {
        try {
            $anticipos = $this->service->listar(
                $request->user()->empresa_activa_id,
                $request->query('cliente_id') ? (int) $request->query('cliente_id') : null
            );
            return response()->json(['success' => true, 'data' => $anticipos]);
        } catch (ComercialException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => MensajeErrorGenerico::desde($e)], 400);
        }
    }
}
