<?php

namespace App\Domains\Core\Observers;

use App\Domains\Core\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Observer generico para modelos PII (Ley 21.719 — Fase 3).
 *
 * CRITICO: NUNCA almacena valores de campos PII, solo nombres de campos.
 * Los modelos con CipherSweet devuelven texto plano en getChanges(); por ello
 * solo se registra array_keys($model->getChanges()), nunca los valores.
 */
class AuditoriaPiiObserver
{
    public function created(Model $model): void
    {
        $this->registrar($model, 'CREAR', 'Registro creado');
    }

    public function updated(Model $model): void
    {
        // Solo registrar si hubieron cambios reales (excluir updated_at/created_at)
        $campos = $this->camposModificados($model);

        if (empty($campos)) {
            return;
        }

        $detalle = 'Campos modificados: ' . implode(', ', $campos);
        $this->registrar($model, 'ACTUALIZAR', $detalle);
    }

    public function deleted(Model $model): void
    {
        $this->registrar($model, 'ELIMINAR', 'Registro eliminado');
    }

    /**
     * Devuelve solo los nombres de campos modificados, excluyendo timestamps.
     * NUNCA incluye valores.
     */
    private function camposModificados(Model $model): array
    {
        $excluir = ['updated_at', 'created_at'];
        $cambios = array_keys($model->getChanges());

        return array_values(array_diff($cambios, $excluir));
    }

    /**
     * Escribe la fila de auditoria. Cualquier fallo se loguea pero NO propaga
     * para no interrumpir la operacion de negocio.
     */
    private function registrar(Model $model, string $operacion, string $detalle): void
    {
        // Nunca auditarse a si mismo para evitar recursion infinita
        if ($model instanceof Auditoria) {
            return;
        }

        try {
            Auditoria::create([
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'nombre_usuario' => auth()->user()?->nombre ?? 'Sistema',
                'operacion'      => $operacion,
                'estado_anterior' => null,
                'estado_nuevo'    => null,
                'detalle'         => $detalle,
                'referencia_cruzada' => (string) ($model->empresa_id ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditoriaPiiObserver: fallo al escribir auditoria', [
                'model'     => get_class($model),
                'id'        => $model->getKey(),
                'operacion' => $operacion,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
