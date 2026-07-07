<?php

namespace App\Domains\Core\Observers;

use App\Domains\Core\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;
use WeakMap;

/** Observer genérico para modelos PII (Ley 21.719): CRITICO, NUNCA almacena valores de campos PII, solo nombres; en CipherSweet getDirty() over-reporta por el cifrado previo a "updating", por eso se captura vía reflexión y se guarda en un WeakMap estático (sobrevive entre instancias del observer sin memory leak) para usarlo en "updated()". */
class AuditoriaPiiObserver
{
    /** WeakMap<Model, list<string>> — campos realmente modificados capturados en updating() para usarlos en updated(). */
    private static WeakMap $pendingCampos;

    public function __construct()
    {
        if (! isset(self::$pendingCampos)) {
            self::$pendingCampos = new WeakMap();
        }
    }

    public function created(Model $model): void
    {
        $this->registrar($model, 'CREAR', 'Registro creado');
    }

    /** Captura los campos realmente sucios antes de "updated": en CipherSweet ya vienen cifrados, por eso se leen pre-cifrado vía reflexión de $cipherSweetSavingUnencryptedAttributes; sin CipherSweet basta getDirty(). */
    public function updating(Model $model): void
    {
        $excluir = ['updated_at', 'created_at'];

        if ($model instanceof CipherSweetEncrypted) {
            // Propiedad protegida que Spatie rellena en su listener "saving" con los campos sucios PRE-cifrado: única fuente de verdad precisa.
            try {
                $ref = new \ReflectionProperty($model, 'cipherSweetSavingUnencryptedAttributes');
                $ref->setAccessible(true);
                $preEncryptDirty = $ref->getValue($model);
            } catch (\ReflectionException) {
                // Fallback por si la propiedad desaparece en una versión futura de Spatie.
                $preEncryptDirty = $model->getDirty();
            }

            $campos = array_values(array_diff(array_keys($preEncryptDirty), $excluir));
        } else {
            $campos = array_values(array_diff(array_keys($model->getDirty()), $excluir));
        }

        self::$pendingCampos[$model] = $campos;
    }

    public function updated(Model $model): void
    {
        // Si no hay entrada capturada en updating() (p.ej. fue suprimido), cae al comportamiento legacy.
        $campos = self::$pendingCampos[$model]
            ?? $this->camposModificadosLegacy($model);

        unset(self::$pendingCampos[$model]);

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

    /** Fallback si updating() no se ejecutó: usa getChanges() (puede over-reportar campos cifrados de CipherSweet), nunca incluye valores. */
    private function camposModificadosLegacy(Model $model): array
    {
        $excluir = ['updated_at', 'created_at'];
        $cambios = array_keys($model->getChanges());

        return array_values(array_diff($cambios, $excluir));
    }

    /** Escribe la fila de auditoría; cualquier fallo se loguea pero NO propaga, para no interrumpir la operación de negocio. */
    private function registrar(Model $model, string $operacion, string $detalle): void
    {
        // Nunca auditarse a si mismo para evitar recursion infinita.
        if ($model instanceof Auditoria) {
            return;
        }

        try {
            Auditoria::create([
                'auditable_type' => get_class($model),
                'auditable_id'   => $model->getKey(),
                'nombre_usuario' => auth()->user()->nombre ?? 'Sistema',
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
