<?php

namespace App\Domains\Core\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Health check en /api/health (BD, cache, queue, storage) sin SSH ni auth; en staging/producción omite el detalle para no exponer versiones ni errores internos. */
class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => ['ok' => true],
            'database' => $this->verificar(fn () => DB::select('select 1') !== []),
            'cache' => $this->verificar(function () {
                $clave = 'health_' . bin2hex(random_bytes(4));
                Cache::put($clave, 'ok', 5);
                $ok = Cache::get($clave) === 'ok';
                Cache::forget($clave);

                return $ok;
            }),
            'queue' => $this->verificar(fn () => is_string(config('queue.default')) && Queue::size() >= 0),
            'storage' => $this->verificar(function () {
                $archivo = 'health/' . bin2hex(random_bytes(4)) . '.txt';
                Storage::put($archivo, 'ok');
                $ok = Storage::get($archivo) === 'ok';
                Storage::delete($archivo);

                return $ok;
            }),
        ];

        $saludable = collect($checks)->every(fn ($check) => $check['ok'] === true);

        // En producción/staging no se expone detalle, solo status + HTTP code; en local ayuda a diagnosticar.
        $detalle = app()->environment('local') ? $checks : [];

        $cuerpo = ['status' => $saludable ? 'ok' : 'degraded'];

        if ($detalle !== []) {
            $cuerpo['checks'] = $detalle;
            $cuerpo['time'] = now()->toIso8601String();
        }

        return response()->json($cuerpo, $saludable ? 200 : 503);
    }

    private function verificar(callable $prueba): array
    {
        try {
            return ['ok' => (bool) $prueba()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
