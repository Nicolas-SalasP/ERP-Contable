<?php

namespace App\Domains\Core\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EmpresaScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin usuario autenticado (jobs, consola, seeders, tests sin actingAs) no hay
        // tenant resoluble: el scope no filtra y el aislamiento debe garantizarse de
        // forma explicita en ese contexto (ver auditoria, hardening P1).
        if (!auth()->check()) {
            return;
        }

        // Fase 2 multitenant: la empresa activa se resuelve del lado servidor desde
        // empresa_activa_id (columna del usuario, nunca de un header/param del cliente).
        // Para usuarios de una sola empresa, empresa_activa_id == empresa_id siempre.
        $empresaId = auth()->user()->empresa_activa_id;

        // Fail-safe: un usuario autenticado SIN empresa activa asignada (p. ej. recien
        // provisionado, en onboarding) NO debe ver datos de ninguna empresa. Antes
        // este caso desactivaba el filtro y exponia todas las empresas.
        if ($empresaId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable() . '.empresa_id', $empresaId);
    }
}
