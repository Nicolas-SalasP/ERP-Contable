<?php

namespace App\Domains\Core\Controllers;

use App\Domains\Core\Models\Auditoria;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoint DPO de consulta de auditoría PII (Ley 21.719); filtra por empresa del solicitante vía referencia_cruzada, requiere permiso usuarios.gestionar. */
class AuditoriaController extends Controller
{
    /** Alias de clase corta a FQCN para el filtro auditable_type. */
    private const ALIAS_TIPOS = [
        'empleado'                => \App\Domains\Rrhh\Models\Empleado::class,
        'contrato'                => \App\Domains\Rrhh\Models\Contrato::class,
        'liquidacion'             => \App\Domains\Rrhh\Models\Liquidacion::class,
        'carga_familiar'          => \App\Domains\Rrhh\Models\CargaFamiliar::class,
        'cuenta_bancaria_empresa' => \App\Domains\Tesoreria\Models\CuentaBancariaEmpresa::class,
        'cuenta_bancaria_proveedor' => \App\Domains\Tesoreria\Models\CuentaBancariaProveedor::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'operacion'      => 'nullable|string|in:CREAR,ACTUALIZAR,ELIMINAR,LECTURA',
            'auditable_type' => 'nullable|string',
            'desde'          => 'nullable|date',
            'hasta'          => 'nullable|date|after_or_equal:desde',
        ]);

        $empresaId = (string) $request->user()->empresa_activa_id;

        $query = Auditoria::query()
            ->where('referencia_cruzada', $empresaId)
            ->orderByDesc('created_at');

        if (!empty($datos['operacion'])) {
            $query->where('operacion', $datos['operacion']);
        }

        if (!empty($datos['auditable_type'])) {
            $tipo = $datos['auditable_type'];
            // Aceptar alias corto o FQCN completo
            $fqcn = self::ALIAS_TIPOS[strtolower($tipo)] ?? $tipo;
            $query->where('auditable_type', $fqcn);
        }

        if (!empty($datos['desde'])) {
            $query->whereDate('created_at', '>=', $datos['desde']);
        }

        if (!empty($datos['hasta'])) {
            $query->whereDate('created_at', '<=', $datos['hasta']);
        }

        return response()->json([
            'success' => true,
            'data'    => $query->paginate(50),
        ]);
    }
}
