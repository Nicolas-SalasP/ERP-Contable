<?php

namespace App\Observers;

use App\Domains\Core\Models\Empresa;
use App\Domains\Contabilidad\Models\CatalogoPlanMaestro;
use App\Domains\Contabilidad\Models\PlanCuenta;

class EmpresaObserver
{
    public function created(Empresa $empresa): void
    {
        $cuentasMaestras = CatalogoPlanMaestro::all();
        $cuentasClonadas = [];

        foreach ($cuentasMaestras as $cuenta) {
            $cuentasClonadas[] = [
                'empresa_id' => $empresa->id,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'tipo' => $cuenta->tipo,
                'nivel' => $cuenta->nivel,
                'imputable' => $cuenta->imputable,
                'activo' => true,
            ];
        }

        if (!empty($cuentasClonadas)) {
            PlanCuenta::insert($cuentasClonadas);
        }
    }

    public function updated(Empresa $empresa): void
    {
        //
    }

    public function deleted(Empresa $empresa): void
    {
        //
    }

    public function restored(Empresa $empresa): void
    {
        //
    }

    public function forceDeleted(Empresa $empresa): void
    {
        //
    }
}
