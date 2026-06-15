<?php

namespace App\Domains\Core\Observers;

use App\Domains\Core\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelCipherSweet\Contracts\CipherSweetEncrypted;
use WeakMap;

/**
 * Observer generico para modelos PII (Ley 21.719 — Fase 3).
 *
 * CRITICO: NUNCA almacena valores de campos PII, solo nombres de campos.
 *
 * ---- Precisión para modelos CipherSweet ----
 * El evento "saving" de Spatie (registrado vía bootUsesCipherSweet al hacer
 * Model::boot) se registra ANTES que los hooks de este observer, por lo que
 * al llegar a "updating" o "saving" en este observer los atributos ya están
 * cifrados en memoria y getDirty() over-reporta todos los campos cifrados.
 *
 * Spatie guarda la lista de campos realmente sucios (pre-cifrado) en la
 * propiedad protegida $cipherSweetSavingUnencryptedAttributes. La leemos vía
 * reflexión en "updating()" y la guardamos en $pendingCampos (WeakMap estática)
 * para usarla en "updated()". Así el detalle contiene solo los campos que el
 * usuario cambió realmente, sin over-report de los campos cifrados que se
 * rescifran en cada save aunque no hayan variado.
 *
 * Para modelos sin CipherSweet seguimos usando getDirty() en "updating()".
 *
 * La WeakMap debe ser STATIC porque Laravel resuelve el observer con
 * container->make() en cada evento (instancia nueva por evento). La WeakMap
 * estática sobrevive entre instancias pero libera entradas automáticamente
 * cuando el modelo ya no tiene referencias (sin memory leak).
 */
class AuditoriaPiiObserver
{
    /**
     * WeakMap<Model, list<string>> — campos realmente modificados capturados
     * en updating() para usarlos en updated().
     */
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

    /**
     * Captura los campos realmente sucios antes de que el evento "updated" sea emitido.
     *
     * Para modelos CipherSweet: el evento "saving" de Spatie ya cifró los atributos
     * ANTES de que "updating" se dispare (Spatie se registra primero durante el boot
     * del modelo). Sin embargo, Spatie almacena la lista de campos sucios pre-cifrado
     * en la propiedad protegida $cipherSweetSavingUnencryptedAttributes. La leemos vía
     * reflexión para obtener la lista exacta de campos que el usuario realmente cambió.
     *
     * Para modelos sin CipherSweet: getDirty() en "updating" es fiel (no hay cifrado).
     */
    public function updating(Model $model): void
    {
        $excluir = ['updated_at', 'created_at'];

        if ($model instanceof CipherSweetEncrypted) {
            // Leer la propiedad protegida que Spatie rellena en su listener "saving"
            // con los campos sucios PRE-cifrado. Es la única fuente de verdad precisa.
            try {
                $ref = new \ReflectionProperty($model, 'cipherSweetSavingUnencryptedAttributes');
                $ref->setAccessible(true);
                $preEncryptDirty = $ref->getValue($model);
            } catch (\ReflectionException) {
                // Fallback: si la propiedad desaparece en una versión futura de Spatie
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
        // Usar la lista capturada en updating(); si no hay entrada (p.ej. updating
        // fue suprimido por algún motivo), caemos al comportamiento legacy.
        $campos = self::$pendingCampos[$model]
            ?? $this->camposModificadosLegacy($model);

        // Limpiar la entrada del WeakMap
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

    /**
     * Fallback para el caso en que updating() no se haya ejecutado.
     * Usa getChanges() (puede over-reportar campos cifrados de CipherSweet).
     * NUNCA incluye valores.
     */
    private function camposModificadosLegacy(Model $model): array
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
