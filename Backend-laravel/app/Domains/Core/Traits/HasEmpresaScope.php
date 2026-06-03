<?php

namespace App\Domains\Core\Traits;

use App\Domains\Core\Scopes\EmpresaScope;

trait HasEmpresaScope
{
    protected static function bootHasEmpresaScope(): void
    {
        static::addGlobalScope(new EmpresaScope);
    }
}
