<?php

namespace App\Observers;

use App\Domains\Core\Models\Empresa;
use App\Domains\Contabilidad\Models\CatalogoPlanMaestro;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta;
use Illuminate\Support\Facades\Log;

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
        try {
            $aplicaCm = $empresa->regimen_tributario !== '14_D8';

            $config = CmConfiguracionEmpresa::create([
                'empresa_id' => $empresa->id,
                'aplica_cm' => $aplicaCm,
                'modalidad' => 'anual',
                'mes_cierre' => 12,
                'cuenta_activos_codigo' => config('correccion_monetaria.cuentas_default.activos', '811001'),
                'cuenta_depreciacion_codigo' => config('correccion_monetaria.cuentas_default.depreciacion', '821001'),
                'cuenta_patrimonio_codigo' => config('correccion_monetaria.cuentas_default.patrimonio', '311406'),
                'cuenta_existencias_codigo' => config('correccion_monetaria.cuentas_default.existencias', '811002'),
                'cuenta_pasivos_codigo' => config('correccion_monetaria.cuentas_default.pasivos', '821002'),
                'activo' => true,
            ]);

            $rolesDefault = config('correccion_monetaria.roles_default_cuentas', []);
            $codigosExistentes = PlanCuenta::where('empresa_id', $empresa->id)
                ->whereIn('codigo', array_keys($rolesDefault))
                ->pluck('codigo')
                ->toArray();

            $configCuentas = [];
            foreach ($codigosExistentes as $codigo) {
                if (isset($rolesDefault[$codigo])) {
                    $configCuentas[] = [
                        'empresa_id' => $empresa->id,
                        'cuenta_codigo' => $codigo,
                        'rol_cm' => $rolesDefault[$codigo],
                        'aplica' => true,
                        'factor_override' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($configCuentas)) {
                CmConfiguracionCuenta::insert($configCuentas);
            }

        } catch (\Exception $e) {
            Log::warning("EmpresaObserver: no se pudo crear config CM para empresa {$empresa->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Empresa $empresa): void
    {
        if ($empresa->isDirty('regimen_tributario') && $empresa->regimen_tributario === '14_D8') {
            CmConfiguracionEmpresa::where('empresa_id', $empresa->id)
                ->update(['aplica_cm' => false]);
        }
        if ($empresa->isDirty('regimen_tributario') && $empresa->getOriginal('regimen_tributario') === '14_D8') {
            CmConfiguracionEmpresa::where('empresa_id', $empresa->id)
                ->update(['aplica_cm' => true]);
        }
    }

    public function deleted(Empresa $empresa): void
    {
    }
    public function restored(Empresa $empresa): void
    {
    }
    public function forceDeleted(Empresa $empresa): void
    {
    }
}
