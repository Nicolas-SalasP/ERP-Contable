<?php

namespace App\Domains\Inventario\Services;

use App\Domains\Core\Models\User;
use App\Domains\Inventario\Models\InventarioAuditoriaEvento;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class InventarioAuditoriaService
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

    public function __construct(private readonly InventarioPermisoService $permisos)
    {
    }

    public function registrarEvento(?User $usuario, array $datos, bool $silencioso = false): ?InventarioAuditoriaEvento
    {
        try {
            $payload = $this->normalizarPayload($usuario, $datos);
            return InventarioAuditoriaEvento::create($payload);
        } catch (Throwable $e) {
            if (!$silencioso) {
                throw $e;
            }

            Log::warning('No se pudo registrar evento de auditoría de inventario.', [
                'accion' => $datos['accion'] ?? null,
                'entidad_tipo' => $datos['entidad_tipo'] ?? null,
                'entidad_id' => $datos['entidad_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function registrarDesdeRequest(Request $request, string $accion, array $datos): ?InventarioAuditoriaEvento
    {
        return $this->registrarEvento($request->user(), array_merge($datos, [
            'accion' => $accion,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));
    }

    public function registrarCambio(
        ?User $usuario,
        string $accion,
        Model $modelo,
        array $antes,
        array $despues,
        array $metadata = []
    ): ?InventarioAuditoriaEvento {
        return $this->registrarEvento($usuario, [
            'accion' => $accion,
            'empresa_id' => $modelo->getAttribute('empresa_id') ?? $usuario?->empresa_id,
            'entidad_tipo' => $modelo::class,
            'entidad_id' => $modelo->getKey(),
            'descripcion' => 'Cambio auditado en ' . class_basename($modelo::class),
            'metadata_json' => $metadata,
            'antes_json' => $antes,
            'despues_json' => $despues,
        ]);
    }

    public function listar(User $usuario, array $filtros = []): LengthAwarePaginator
    {
        $this->permisos->exigir($usuario, 'inventario.auditoria.ver');

        return $this->queryFiltrada($usuario, $filtros)
            ->with('usuario:id,nombre,email,empresa_id,rol_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->normalizarPerPage($filtros['per_page'] ?? 20));
    }

    public function obtener(User $usuario, int $id): InventarioAuditoriaEvento
    {
        $this->permisos->exigir($usuario, 'inventario.auditoria.detalle');

        $evento = InventarioAuditoriaEvento::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->with('usuario:id,nombre,email,empresa_id,rol_id')
            ->find($id);

        if (!$evento) {
            throw new Exception('El evento de auditoría no existe o no pertenece a la empresa.');
        }

        return $evento;
    }

    public function resumen(User $usuario, array $filtros = []): array
    {
        $this->permisos->exigir($usuario, 'inventario.auditoria.resumen');

        $base = $this->queryFiltrada($usuario, $filtros);

        return [
            'total_eventos' => (clone $base)->count(),
            'por_severidad' => (clone $base)
                ->selectRaw('severidad, COUNT(*) as total')
                ->groupBy('severidad')
                ->pluck('total', 'severidad')
                ->toArray(),
            'por_accion' => (clone $base)
                ->selectRaw('accion, COUNT(*) as total')
                ->groupBy('accion')
                ->orderByDesc('total')
                ->limit(20)
                ->pluck('total', 'accion')
                ->toArray(),
            'por_usuario' => (clone $base)
                ->selectRaw('usuario_id, COUNT(*) as total')
                ->groupBy('usuario_id')
                ->orderByDesc('total')
                ->limit(20)
                ->pluck('total', 'usuario_id')
                ->toArray(),
            'ultimos_criticos' => (clone $base)
                ->where('severidad', InventarioAuditoriaEvento::SEVERIDAD_CRITICAL)
                ->with('usuario:id,nombre,email')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ];
    }

    private function queryFiltrada(User $usuario, array $filtros = []): Builder
    {
        return InventarioAuditoriaEvento::query()
            ->where('empresa_id', $usuario->empresa_id)
            ->when(!empty($filtros['accion']), fn (Builder $query) => $query->where('accion', $filtros['accion']))
            ->when(!empty($filtros['entidad_tipo']), fn (Builder $query) => $query->where('entidad_tipo', $filtros['entidad_tipo']))
            ->when(!empty($filtros['entidad_id']), fn (Builder $query) => $query->where('entidad_id', (int) $filtros['entidad_id']))
            ->when(!empty($filtros['usuario_id']), fn (Builder $query) => $query->where('usuario_id', (int) $filtros['usuario_id']))
            ->when(!empty($filtros['severidad']), fn (Builder $query) => $query->where('severidad', $filtros['severidad']))
            ->when(!empty($filtros['modulo']), fn (Builder $query) => $query->where('modulo', $filtros['modulo']))
            ->when(!empty($filtros['origen_modulo']), fn (Builder $query) => $query->where('origen_modulo', $filtros['origen_modulo']))
            ->when(!empty($filtros['origen_id']), fn (Builder $query) => $query->where('origen_id', (int) $filtros['origen_id']))
            ->when(!empty($filtros['fecha_desde']), fn (Builder $query) => $query->whereDate('created_at', '>=', $filtros['fecha_desde']))
            ->when(!empty($filtros['fecha_hasta']), fn (Builder $query) => $query->whereDate('created_at', '<=', $filtros['fecha_hasta']));
    }

    private function normalizarPayload(?User $usuario, array $datos): array
    {
        $empresaId = $datos['empresa_id'] ?? $usuario?->empresa_id;

        if ($empresaId === null) {
            throw ValidationException::withMessages(['empresa_id' => 'La empresa es obligatoria para auditar eventos de inventario.']);
        }

        $accion = (string) ($datos['accion'] ?? '');
        if (!in_array($accion, InventarioAuditoriaEvento::accionesPermitidas(), true)) {
            throw ValidationException::withMessages(['accion' => 'La acción de auditoría no es válida.']);
        }

        $severidad = $datos['severidad'] ?? InventarioAuditoriaEvento::SEVERIDAD_INFO;
        if (!in_array($severidad, InventarioAuditoriaEvento::severidadesPermitidas(), true)) {
            throw ValidationException::withMessages(['severidad' => 'La severidad de auditoría no es válida.']);
        }

        $estado = $datos['estado'] ?? InventarioAuditoriaEvento::ESTADO_REGISTRADO;
        if (!in_array($estado, InventarioAuditoriaEvento::estadosPermitidos(), true)) {
            throw ValidationException::withMessages(['estado' => 'El estado de auditoría no es válido.']);
        }

        return [
            'empresa_id' => (int) $empresaId,
            'usuario_id' => $datos['usuario_id'] ?? $usuario?->id,
            'modulo' => $this->texto($datos['modulo'] ?? InventarioAuditoriaEvento::MODULO_INVENTARIO, 40) ?? InventarioAuditoriaEvento::MODULO_INVENTARIO,
            'accion' => $accion,
            'entidad_tipo' => $this->texto($datos['entidad_tipo'] ?? null, 120) ?? 'inventario',
            'entidad_id' => isset($datos['entidad_id']) ? (int) $datos['entidad_id'] : null,
            'severidad' => $severidad,
            'estado' => $estado,
            'descripcion' => $this->texto($datos['descripcion'] ?? null, 500) ?? $accion,
            'ip' => $this->texto($datos['ip'] ?? request()?->ip(), 64),
            'user_agent' => $this->texto($datos['user_agent'] ?? request()?->userAgent(), 500),
            'referencia' => $this->texto($datos['referencia'] ?? null, 160),
            'motivo' => $this->texto($datos['motivo'] ?? null, 160),
            'observacion' => $this->texto($datos['observacion'] ?? null, 2000),
            'origen_modulo' => $this->texto($datos['origen_modulo'] ?? null, 80),
            'origen_id' => isset($datos['origen_id']) ? (int) $datos['origen_id'] : null,
            'metadata_json' => $this->sanearJson($datos['metadata_json'] ?? $datos['metadata'] ?? null),
            'antes_json' => $this->sanearJson($datos['antes_json'] ?? $datos['antes'] ?? null),
            'despues_json' => $this->sanearJson($datos['despues_json'] ?? $datos['despues'] ?? null),
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
            return $valor;
        }

        return $this->sanearArray($valor);
    }

    private function sanearArray(array $datos): array
    {
        $limpio = [];

        foreach ($datos as $clave => $valor) {
            $claveNormalizada = strtolower(str_replace(['-', '_'], '', (string) $clave));

            if (in_array($claveNormalizada, self::CLAVES_SENSIBLES, true) || in_array(strtolower((string) $clave), self::CLAVES_SENSIBLES, true)) {
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
