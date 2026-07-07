<?php

namespace App\Domains\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin usuario autenticado (jobs, consola, seeders, tests sin actingAs) no hay tenant resoluble; el aislamiento debe garantizarse explicitamente en ese contexto.
        if (!auth()->check()) {
            return;
        }

        // La empresa activa se resuelve del lado servidor desde empresa_activa_id, nunca de un header/param del cliente.
        $empresaId = auth()->user()->empresa_activa_id;

        // Fail-safe: usuario autenticado sin empresa activa (ej. recien provisionado) no debe ver datos de ninguna empresa; antes esto exponia todas.
        if ($empresaId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable() . '.empresa_id', $empresaId);
    }
}
