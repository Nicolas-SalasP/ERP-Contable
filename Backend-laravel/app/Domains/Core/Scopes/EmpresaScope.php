<?php

namespace App\Domains\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin usuario autenticado (rutas publicas, jobs, consola, tests sin actingAs) no se filtra.
        if (auth()->check() && auth()->user()->empresa_id !== null) {
            $builder->where($model->getTable() . '.empresa_id', auth()->user()->empresa_id);
        }
    }
}
