<?php

namespace Database\Seeders;

use App\Domains\Core\Models\Empresa;
use App\Domains\Rrhh\Models\RrhhMapeoContable;
use Illuminate\Database\Seeder;

class RrhhMapeoContableSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::first();
        if (!$empresa) {
            return;
        }

        $mapeos = [
            // DEBE — gastos del período
            [
                'tipo_cuenta'            => RrhhMapeoContable::GASTO_REMUNERACIONES,
                'cuenta_contable_codigo' => '621005',
                'descripcion'            => 'Gasto Remuneraciones',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::GASTO_LEYES_SOCIALES,
                'cuenta_contable_codigo' => '621010',
                'descripcion'            => 'Gasto Leyes Sociales Empleador (SIS + AFC + Mutual)',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::GASTO_PROVISION_VACACIONES,
                'cuenta_contable_codigo' => '621015',
                'descripcion'            => 'Gasto Provision Vacaciones',
            ],

            // HABER — pasivos al cierre del período
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_LIQUIDO_PAGAR,
                'cuenta_contable_codigo' => '353205',
                'descripcion'            => 'Remuneraciones por pagar (liquido trabajadores)',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_RETENCIONES_PREVISIONALES,
                'cuenta_contable_codigo' => '353248',
                'descripcion'            => 'Retenciones Previsionales por pagar (AFP + Salud + AFC trabajador)',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_IMPUESTO_UNICO,
                'cuenta_contable_codigo' => '353242',
                'descripcion'            => 'Impuesto Unico Trabajadores retenido por pagar',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_LEYES_SOCIALES,
                'cuenta_contable_codigo' => '353250',
                'descripcion'            => 'Leyes Sociales Empleador por pagar (SIS + AFC + Mutual)',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_DESCUENTOS_VOLUNTARIOS,
                'cuenta_contable_codigo' => '353255',
                'descripcion'            => 'Descuentos Voluntarios por pagar (APV, prestamos, etc.)',
            ],
            [
                'tipo_cuenta'            => RrhhMapeoContable::PASIVO_PROVISION_VACACIONES,
                'cuenta_contable_codigo' => '353260',
                'descripcion'            => 'Provision Vacaciones por pagar',
            ],
        ];

        foreach ($mapeos as $mapeo) {
            RrhhMapeoContable::updateOrCreate(
                [
                    'empresa_id' => $empresa->id,
                    'tipo_cuenta' => $mapeo['tipo_cuenta'],
                ],
                [
                    'cuenta_contable_codigo' => $mapeo['cuenta_contable_codigo'],
                    'descripcion'            => $mapeo['descripcion'],
                ]
            );
        }
    }
}
