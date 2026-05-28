<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Integracion\Contratos\InventarioEventoIntegracionContrato;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use App\Domains\Inventario\Models\InventarioEventoIntegracion;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventarioEventoIntegracionService implements InventarioEventoIntegracionContrato
{
    private const CLAVES_SENSIBLES = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'set-cookie',
        'x-xsrf-token',
        'plaintexttoken',
        'currentaccesstoken',
        'api_key',
        'apikey',
        'secret',
        '.env',
    ];

    private const CLAVES_TRIBUTARIAS_BLOQUEADAS = [
        'codigo_dte',
        'codigo_sii',
        'folio_dte',
        'xml_dte',
        'track_id_sii',
        'guia_despacho_electronica',
        'factura_electronica',
        'boleta_electronica',
        'emitir_dte',
        'estado_sii',
    ];

    public function __construct(
        private readonly InventarioPermisoService $permisos,
        private readonly InventarioAuditoriaService $auditoria
    ) {
    }

    public function publicarEvento(?User $usuario, array $datos, bool $silencioso = false): ?InventarioEventoIntegracion
    {
        try {
            $payload = $this->normalizarPayload($usuario, $datos);

            return InventarioEventoIntegracion::create($payload);
        } catch (Throwable $e) {
            if (!$silencioso) {
                throw $e;
            }

            Log::warning('No se pudo publicar evento interno de integración de inventario.', [
                'evento' => $datos['evento'] ?? null,
                'entidad_tipo' => $datos['entidad_tipo'] ?? null,
                'entidad_id' => $datos['entidad_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function publicarDesdeRequest(Request $request, string $evento, array $datos, bool $silencioso = false): ?InventarioEventoIntegracion
    {
        $metadata = $datos['metadata_json'] ?? $datos['metadata'] ?? [];
        $metadata = is_array($metadata) ? $metadata : ['valor' => $metadata];

        return $this->publicarEvento($request->user(), array_merge($datos, [
            'evento' => $evento,
            'metadata_json' => array_merge($metadata, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
        ]), $silencioso);
    }

    public function publicarDesdeOperacion(?User $usuario, string $evento, array $datos, bool $silencioso = true): ?InventarioEventoIntegracion
    {
        return $this->publicarEvento($usuario, array_merge($datos, [
            'evento' => $evento,
        ]), $silencioso);
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.eventos_integracion.ver');

        return $this->queryFiltrada($usuario, $filtros)
            ->with('usuario:id,nombre,email,empresa_id,rol_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 20));
    }

    public function obtener(User $usuario, int $id): InventarioEventoIntegracion
    {
        $this->permisos->exigir($usuario, 'inventario.eventos_integracion.detalle');

        $evento = InventarioEventoIntegracion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with('usuario:id,nombre,email,empresa_id,rol_id')
            ->find($id);

        if (!$evento) {
            throw new Exception('El evento de integración no existe o no pertenece a la empresa.');
        }

        return $evento;
    }

    public function resumen(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.eventos_integracion.resumen');

        $base = $this->queryFiltrada($usuario, $filtros);

        return [
            'total_eventos' => (clone $base)->count(),
            'pendientes' => (clone $base)->where('estado', InventarioEventoIntegracion::ESTADO_PENDIENTE)->count(),
            'errores' => (clone $base)->where('estado', InventarioEventoIntegracion::ESTADO_ERROR)->count(),
            'por_estado' => (clone $base)
                ->selectRaw('estado, COUNT(*) as total')
                ->groupBy('estado')
                ->pluck('total', 'estado')
                ->toArray(),
            'por_prioridad' => (clone $base)
                ->selectRaw('prioridad, COUNT(*) as total')
                ->groupBy('prioridad')
                ->pluck('total', 'prioridad')
                ->toArray(),
            'por_evento' => (clone $base)
                ->selectRaw('evento, COUNT(*) as total')
                ->groupBy('evento')
                ->orderByDesc('total')
                ->limit(20)
                ->pluck('total', 'evento')
                ->toArray(),
            'ultimos_errores' => (clone $base)
                ->where('estado', InventarioEventoIntegracion::ESTADO_ERROR)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    public function marcarProcesado(User $usuario, int $id): InventarioEventoIntegracion
    {
        $this->permisos->exigirAlguno($usuario, [
            'inventario.eventos_integracion.procesar',
            'inventario.eventos_integracion.gestionar',
        ]);

        $evento = $this->obtenerGestionable($usuario, $id);
        $evento->update([
            'estado' => InventarioEventoIntegracion::ESTADO_PROCESADO,
            'procesado_at' => now(),
            'error_mensaje' => null,
        ]);

        $evento = $evento->refresh();
        $this->auditarGestion($usuario, $evento, InventarioAuditoriaEvento::ACCION_EVENTO_INTEGRACION_PROCESADO);

        return $evento;
    }

    public function marcarIgnorado(User $usuario, int $id, ?string $motivo = null): InventarioEventoIntegracion
    {
        $this->permisos->exigir($usuario, 'inventario.eventos_integracion.gestionar');

        $evento = $this->obtenerGestionable($usuario, $id);
        $metadata = $evento->metadata_json ?? [];
        $metadata['ignorado_por_usuario_id'] = $usuario->id;
        $metadata['motivo_ignorado'] = $this->texto($motivo, 500);

        $evento->update([
            'estado' => InventarioEventoIntegracion::ESTADO_IGNORADO,
            'metadata_json' => $this->sanearJson($metadata),
            'procesado_at' => now(),
            'error_mensaje' => null,
        ]);

        $evento = $evento->refresh();
        $this->auditarGestion($usuario, $evento, InventarioAuditoriaEvento::ACCION_EVENTO_INTEGRACION_IGNORADO);

        return $evento;
    }

    public function marcarError(User $usuario, int $id, string $mensaje): InventarioEventoIntegracion
    {
        $this->permisos->exigir($usuario, 'inventario.eventos_integracion.gestionar');

        $evento = $this->obtenerGestionable($usuario, $id);
        $evento->update([
            'estado' => InventarioEventoIntegracion::ESTADO_ERROR,
            'error_mensaje' => $this->texto($mensaje, 2000),
        ]);

        $evento = $evento->refresh();
        $this->auditarGestion($usuario, $evento, InventarioAuditoriaEvento::ACCION_EVENTO_INTEGRACION_ERROR);

        return $evento;
    }

    private function auditarGestion(User $usuario, InventarioEventoIntegracion $evento, string $accion): void
    {
        $this->auditoria->registrarEvento($usuario, [
            'empresa_id' => $evento->empresa_id,
            'accion' => $accion,
            'entidad_tipo' => InventarioEventoIntegracion::class,
            'entidad_id' => $evento->id,
            'severidad' => $accion === InventarioAuditoriaEvento::ACCION_EVENTO_INTEGRACION_ERROR
                ? InventarioAuditoriaEvento::SEVERIDAD_WARNING
                : InventarioAuditoriaEvento::SEVERIDAD_INFO,
            'descripcion' => 'Gestión administrativa de evento interno de integración de inventario.',
            'metadata_json' => [
                'evento' => $evento->evento,
                'estado' => $evento->estado,
                'prioridad' => $evento->prioridad,
                'correlacion_id' => $evento->correlacion_id,
            ],
            'origen_modulo' => 'inventario.eventos_integracion',
            'origen_id' => $evento->id,
        ], true);
    }

    private function obtenerGestionable(User $usuario, int $id): InventarioEventoIntegracion
    {
        $evento = InventarioEventoIntegracion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->find($id);

        if (!$evento) {
            throw new Exception('El evento de integración no existe o no pertenece a la empresa.');
        }

        return $evento;
    }

    private function queryFiltrada(User $usuario, array $filtros = []): Builder
    {
        return InventarioEventoIntegracion::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->when(!empty($filtros['evento']), fn (Builder $query) => $query->where('evento', $filtros['evento']))
            ->when(!empty($filtros['entidad_tipo']), fn (Builder $query) => $query->where('entidad_tipo', $filtros['entidad_tipo']))
            ->when(!empty($filtros['entidad_id']), fn (Builder $query) => $query->where('entidad_id', (int) $filtros['entidad_id']))
            ->when(!empty($filtros['usuario_id']), fn (Builder $query) => $query->where('usuario_id', (int) $filtros['usuario_id']))
            ->when(!empty($filtros['estado']), fn (Builder $query) => $query->where('estado', $filtros['estado']))
            ->when(!empty($filtros['prioridad']), fn (Builder $query) => $query->where('prioridad', $filtros['prioridad']))
            ->when(!empty($filtros['modulo_origen']), fn (Builder $query) => $query->where('modulo_origen', $filtros['modulo_origen']))
            ->when(!empty($filtros['origen_modulo']), fn (Builder $query) => $query->where('origen_modulo', $filtros['origen_modulo']))
            ->when(!empty($filtros['origen_id']), fn (Builder $query) => $query->where('origen_id', (int) $filtros['origen_id']))
            ->when(!empty($filtros['correlacion_id']), fn (Builder $query) => $query->where('correlacion_id', $filtros['correlacion_id']))
            ->when(!empty($filtros['fecha_desde']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filtros['fecha_desde']))
            ->when(!empty($filtros['fecha_hasta']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filtros['fecha_hasta']));
    }

    private function normalizarPayload(?User $usuario, array $datos): array
    {
        $empresaId = $datos['empresa_id'] ?? $usuario?->empresa_id;

        if ($empresaId === null) {
            throw ValidationException::withMessages(['empresa_id' => 'La empresa es obligatoria para publicar eventos internos de inventario.']);
        }

        $evento = (string) ($datos['evento'] ?? '');
        if (!in_array($evento, InventarioEventoIntegracion::eventosPermitidos(), true)) {
            throw ValidationException::withMessages(['evento' => 'El evento de integración no es válido.']);
        }

        $estado = $datos['estado'] ?? InventarioEventoIntegracion::ESTADO_PENDIENTE;
        if (!in_array($estado, InventarioEventoIntegracion::estadosPermitidos(), true)) {
            throw ValidationException::withMessages(['estado' => 'El estado del evento de integración no es válido.']);
        }

        $prioridad = $datos['prioridad'] ?? InventarioEventoIntegracion::PRIORIDAD_NORMAL;
        if (!in_array($prioridad, InventarioEventoIntegracion::prioridadesPermitidas(), true)) {
            throw ValidationException::withMessages(['prioridad' => 'La prioridad del evento de integración no es válida.']);
        }

        return [
            'empresa_id' => (int) $empresaId,
            'usuario_id' => $datos['usuario_id'] ?? $usuario?->id,
            'evento' => $evento,
            'modulo_origen' => $this->texto($datos['modulo_origen'] ?? InventarioEventoIntegracion::MODULO_ORIGEN_INVENTARIO, 40) ?? InventarioEventoIntegracion::MODULO_ORIGEN_INVENTARIO,
            'entidad_tipo' => $this->texto($datos['entidad_tipo'] ?? null, 120) ?? 'inventario',
            'entidad_id' => isset($datos['entidad_id']) ? (int) $datos['entidad_id'] : null,
            'estado' => $estado,
            'prioridad' => $prioridad,
            'payload_json' => $this->sanearJson($datos['payload_json'] ?? $datos['payload'] ?? null),
            'metadata_json' => $this->sanearJson($datos['metadata_json'] ?? $datos['metadata'] ?? null),
            'correlacion_id' => $this->texto($datos['correlacion_id'] ?? Str::uuid()->toString(), 120),
            'origen_modulo' => $this->texto($datos['origen_modulo'] ?? null, 80),
            'origen_id' => isset($datos['origen_id']) ? (int) $datos['origen_id'] : null,
            'procesado_at' => $estado === InventarioEventoIntegracion::ESTADO_PROCESADO ? now() : null,
            'error_mensaje' => $this->texto($datos['error_mensaje'] ?? null, 2000),
        ];
    }

    private function sanearJson(mixed $valor): mixed
    {
        if ($valor === null) {
            return null;
        }

        if ($valor instanceof Model) {
            $valor = $valor->toArray();
        }

        if (is_object($valor) && method_exists($valor, 'toArray')) {
            $valor = $valor->toArray();
        }

        if (!is_array($valor)) {
            return is_scalar($valor) ? $valor : null;
        }

        return $this->sanearArray($valor);
    }

    private function sanearArray(array $datos): array
    {
        $limpio = [];

        foreach ($datos as $clave => $valor) {
            $claveNormalizada = strtolower(str_replace(['-', '_'], '', (string) $clave));
            $claveOriginal = strtolower((string) $clave);

            if (
                in_array($claveNormalizada, $this->normalizarClavesBloqueadas(self::CLAVES_SENSIBLES), true)
                || in_array($claveOriginal, self::CLAVES_SENSIBLES, true)
                || in_array($claveNormalizada, $this->normalizarClavesBloqueadas(self::CLAVES_TRIBUTARIAS_BLOQUEADAS), true)
                || in_array($claveOriginal, self::CLAVES_TRIBUTARIAS_BLOQUEADAS, true)
            ) {
                continue;
            }

            if (is_array($valor)) {
                $limpio[$clave] = $this->sanearArray($valor);
                continue;
            }

            if ($valor instanceof Model) {
                $limpio[$clave] = $this->sanearArray($valor->toArray());
                continue;
            }

            if (is_object($valor) && method_exists($valor, 'toArray')) {
                $limpio[$clave] = $this->sanearArray($valor->toArray());
                continue;
            }

            if (is_scalar($valor) || $valor === null) {
                $limpio[$clave] = $valor;
            }
        }

        return $limpio;
    }

    private function normalizarClavesBloqueadas(array $claves): array
    {
        return array_map(
            static fn (string $clave): string => strtolower(str_replace(['-', '_'], '', $clave)),
            $claves
        );
    }

    private function texto(mixed $valor, int $max): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        return mb_substr($valor, 0, $max);
    }

    private function normalizarPerPage(mixed $perPage): int
    {
        $perPage = (int) $perPage;
        if ($perPage <= 0) {
            return 20;
        }

        return min($perPage, 200);
    }
}
