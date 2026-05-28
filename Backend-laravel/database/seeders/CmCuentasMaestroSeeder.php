<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\CatalogoPlanMaestro;
use App\Domains\Contabilidad\Models\PlanCuenta;
use App\Domains\Core\Models\Empresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionEmpresa;
use App\Domains\CorreccionMonetaria\Models\CmConfiguracionCuenta;
use Illuminate\Support\Facades\DB;

class CmCuentasMaestroSeeder extends Seeder
{
    public function run(): void
    {
        // =====================================================================
        // 1. AGREGAR CUENTAS AL CATALOGO PLAN MAESTRO
        // =====================================================================
        $cuentasCM = [
            // --- Clase 8: CORRECCIÓN MONETARIA ---
            [
                'codigo'    => '8',
                'nombre'    => 'CORRECCIÓN MONETARIA',
                'tipo'      => 'INGRESO',
                'nivel'     => 1,
                'imputable' => false,
            ],
            [
                'codigo'    => '81',
                'nombre'    => 'INGRESOS POR CORRECCIÓN MONETARIA',
                'tipo'      => 'INGRESO',
                'nivel'     => 2,
                'imputable' => false,
            ],
            [
                'codigo'    => '811001',
                'nombre'    => 'CM Ganancia - Activos No Monetarios',
                'tipo'      => 'INGRESO',
                'nivel'     => 3,
                'imputable' => true,
            ],
            [
                'codigo'    => '811002',
                'nombre'    => 'CM Ganancia - Existencias / Inventarios',
                'tipo'      => 'INGRESO',
                'nivel'     => 3,
                'imputable' => true,
            ],
            [
                'codigo'    => '82',
                'nombre'    => 'GASTOS POR CORRECCIÓN MONETARIA',
                'tipo'      => 'GASTO',
                'nivel'     => 2,
                'imputable' => false,
            ],
            [
                'codigo'    => '821001',
                'nombre'    => 'CM Pérdida - Depreciación Acumulada',
                'tipo'      => 'GASTO',
                'nivel'     => 3,
                'imputable' => true,
            ],
            [
                'codigo'    => '821002',
                'nombre'    => 'CM Pérdida - Pasivos No Monetarios',
                'tipo'      => 'GASTO',
                'nivel'     => 3,
                'imputable' => true,
            ],
            [
                'codigo'    => '821003',
                'nombre'    => 'CM Pérdida - Patrimonio',
                'tipo'      => 'GASTO',
                'nivel'     => 3,
                'imputable' => true,
            ],
        ];

        foreach ($cuentasCM as $cuenta) {
            CatalogoPlanMaestro::updateOrCreate(
                ['codigo' => $cuenta['codigo']],
                $cuenta
            );
        }

        $this->command->info('✓ Cuentas CM agregadas al CatalogoPlanMaestro (' . count($cuentasCM) . ' cuentas).');

        // =====================================================================
        // 2. PROPAGAR A EMPRESAS EXISTENTES (que no tienen estas cuentas)
        // =====================================================================
        $empresas = Empresa::all();
        $codigosCM = array_column($cuentasCM, 'codigo');
        $propagadas = 0;

        foreach ($empresas as $empresa) {
            // Solo propagar cuentas que la empresa todavía no tiene
            $codigosExistentes = PlanCuenta::where('empresa_id', $empresa->id)
                ->whereIn('codigo', $codigosCM)
                ->pluck('codigo')
                ->toArray();

            $codigosFaltantes = array_diff($codigosCM, $codigosExistentes);

            if (empty($codigosFaltantes)) {
                continue;
            }

            $insertar = [];
            foreach ($cuentasCM as $cuenta) {
                if (in_array($cuenta['codigo'], $codigosFaltantes)) {
                    $insertar[] = [
                        'empresa_id' => $empresa->id,
                        'codigo'     => $cuenta['codigo'],
                        'nombre'     => $cuenta['nombre'],
                        'tipo'       => $cuenta['tipo'],
                        'nivel'      => $cuenta['nivel'],
                        'imputable'  => $cuenta['imputable'],
                        'activo'     => true,
                    ];
                }
            }

            if (!empty($insertar)) {
                PlanCuenta::insert($insertar);
                $propagadas++;
            }
        }

        $this->command->info("✓ Cuentas CM propagadas a {$propagadas} empresa(s) existente(s).");

        // =====================================================================
        // 3. CREAR CONFIGURACION CM PARA EMPRESAS EXISTENTES
        // =====================================================================
        $rolesDefault  = config('correccion_monetaria.roles_default_cuentas', []);
        $cuentasDefault = config('correccion_monetaria.cuentas_default', []);
        $configCreadas = 0;

        foreach ($empresas as $empresa) {
            // Si ya tiene configuración, no tocar
            if (CmConfiguracionEmpresa::where('empresa_id', $empresa->id)->exists()) {
                continue;
            }

            $aplicaCm = $empresa->regimen_tributario !== '14_D8';

            DB::transaction(function () use ($empresa, $aplicaCm, $cuentasDefault, $rolesDefault) {
                CmConfiguracionEmpresa::create([
                    'empresa_id'                 => $empresa->id,
                    'aplica_cm'                  => $aplicaCm,
                    'modalidad'                  => 'anual',
                    'mes_cierre'                 => 12,
                    'cuenta_activos_codigo'      => $cuentasDefault['activos']      ?? '811001',
                    'cuenta_depreciacion_codigo' => $cuentasDefault['depreciacion'] ?? '821001',
                    'cuenta_patrimonio_codigo'   => $cuentasDefault['patrimonio']   ?? '311406',
                    'cuenta_existencias_codigo'  => $cuentasDefault['existencias']  ?? '811002',
                    'cuenta_pasivos_codigo'      => $cuentasDefault['pasivos']      ?? '821002',
                    'activo'                     => true,
                ]);

                // Crear configuración de cuentas participantes
                $codigosEnPlan = PlanCuenta::where('empresa_id', $empresa->id)
                    ->whereIn('codigo', array_keys($rolesDefault))
                    ->pluck('codigo')
                    ->toArray();

                $configCuentas = [];
                foreach ($codigosEnPlan as $codigo) {
                    if (isset($rolesDefault[$codigo])) {
                        // Verificar que no exista ya
                        if (!CmConfiguracionCuenta::where('empresa_id', $empresa->id)
                                ->where('cuenta_codigo', $codigo)->exists()) {
                            $configCuentas[] = [
                                'empresa_id'      => $empresa->id,
                                'cuenta_codigo'   => $codigo,
                                'rol_cm'          => $rolesDefault[$codigo],
                                'aplica'          => true,
                                'factor_override' => null,
                                'created_at'      => now(),
                                'updated_at'      => now(),
                            ];
                        }
                    }
                }

                if (!empty($configCuentas)) {
                    CmConfiguracionCuenta::insert($configCuentas);
                }
            });

            $configCreadas++;
        }

        $this->command->info("✓ Configuración CM creada para {$configCreadas} empresa(s) existente(s).");
        $this->command->info('');
        $this->command->info('Fase 1 de Corrección Monetaria completada.');
        $this->command->info('Próximo paso: php artisan db:seed --class=CmCuentasMaestroSeeder');
    }
}
