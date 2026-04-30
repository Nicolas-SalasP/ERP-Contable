<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoPlanMaestroSeeder extends Seeder
{
    public function run(): void
    {
        $cuentas = [
            // --- ACTIVOS (1xxxx) ---
            ['codigo' => '111205', 'nombre' => 'Software', 'tipo' => 'ACTIVO'],
            ['codigo' => '111206', 'nombre' => 'Depreciacion acumulada Software', 'tipo' => 'ACTIVO'],
            ['codigo' => '112005', 'nombre' => 'Edificios', 'tipo' => 'ACTIVO'],
            ['codigo' => '112105', 'nombre' => 'Maquinarias y equipos', 'tipo' => 'ACTIVO'],
            ['codigo' => '112205', 'nombre' => 'Hardware', 'tipo' => 'ACTIVO'],
            ['codigo' => '112210', 'nombre' => 'Vehiculos', 'tipo' => 'ACTIVO'],
            ['codigo' => '112215', 'nombre' => 'Camiones', 'tipo' => 'ACTIVO'],
            ['codigo' => '112220', 'nombre' => 'Muebles e instalaciones', 'tipo' => 'ACTIVO'],
            ['codigo' => '151005', 'nombre' => 'Inventario Materiales', 'tipo' => 'ACTIVO'],
            ['codigo' => '151010', 'nombre' => 'Inventario Insumos', 'tipo' => 'ACTIVO'],
            ['codigo' => '151225', 'nombre' => 'Mercaderia entregada en consignacion', 'tipo' => 'ACTIVO'],
            ['codigo' => '152005', 'nombre' => 'Cuentas por Cobrar Clientes', 'tipo' => 'ACTIVO'],
            ['codigo' => '152045', 'nombre' => 'Cheques en cartera', 'tipo' => 'ACTIVO'],
            ['codigo' => '152406', 'nombre' => 'Fondos por Rendir', 'tipo' => 'ACTIVO'],
            ['codigo' => '152408', 'nombre' => 'Canje divisa', 'tipo' => 'ACTIVO'],
            ['codigo' => '152540', 'nombre' => 'IVA por cobrar', 'tipo' => 'ACTIVO'],
            ['codigo' => '152541', 'nombre' => 'PPM por recuperar', 'tipo' => 'ACTIVO'],
            ['codigo' => '154020', 'nombre' => 'Caja Chica', 'tipo' => 'ACTIVO'],
            ['codigo' => '154205', 'nombre' => 'Banco Santander CLP', 'tipo' => 'ACTIVO'],
            ['codigo' => '154230', 'nombre' => 'Banco Estado CLP', 'tipo' => 'ACTIVO'],
            ['codigo' => '154236', 'nombre' => 'Banco Itau CLP', 'tipo' => 'ACTIVO'],
            ['codigo' => '154240', 'nombre' => 'Banco Security CLP', 'tipo' => 'ACTIVO'],

            // --- PATRIMONIO (31xxx) ---
            ['codigo' => '311005', 'nombre' => 'Resultado Acumulado', 'tipo' => 'PATRIMONIO'],
            ['codigo' => '311406', 'nombre' => 'Correccion Monetaria Patrimonio', 'tipo' => 'PATRIMONIO'],

            // --- PASIVOS (33xxx - 35xxx) ---
            ['codigo' => '331005', 'nombre' => 'Provision Impuesto a la Renta', 'tipo' => 'PASIVO'],
            ['codigo' => '352050', 'nombre' => 'Cheques por pagar', 'tipo' => 'PASIVO'],
            ['codigo' => '352060', 'nombre' => 'Tarjetas de credito', 'tipo' => 'PASIVO'],
            ['codigo' => '352075', 'nombre' => 'Linea sobregiro', 'tipo' => 'PASIVO'],
            ['codigo' => '352105', 'nombre' => 'Cuentas por pagar', 'tipo' => 'PASIVO'],
            ['codigo' => '352130', 'nombre' => 'Facturas por pagar (Puente)', 'tipo' => 'PASIVO'],
            ['codigo' => '352135', 'nombre' => 'Facturas por pagar Honorarios', 'tipo' => 'PASIVO'],
            ['codigo' => '353205', 'nombre' => 'Remuneraciones por pagar', 'tipo' => 'PASIVO'],
            ['codigo' => '353242', 'nombre' => 'Impuesto Unico Trabajadores', 'tipo' => 'PASIVO'],
            ['codigo' => '353245', 'nombre' => 'Retenciones AFP', 'tipo' => 'PASIVO'],
            ['codigo' => '353350', 'nombre' => 'IVA por Cobrar (Transito)', 'tipo' => 'PASIVO'],
            ['codigo' => '353360', 'nombre' => 'IVA por pagar', 'tipo' => 'PASIVO'],
            ['codigo' => '353410', 'nombre' => 'Honorarios devengados por pagar', 'tipo' => 'PASIVO'],

            // --- INGRESOS (5xxxx) ---
            ['codigo' => '501105', 'nombre' => 'Ventas Nacionales', 'tipo' => 'INGRESO'],
            ['codigo' => '501110', 'nombre' => 'Ventas Servicios', 'tipo' => 'INGRESO'],
            ['codigo' => '501205', 'nombre' => 'Ventas - Exportacion', 'tipo' => 'INGRESO'],

            // --- COSTOS Y GASTOS (6xxxx - 9xxxx) ---
            ['codigo' => '601205', 'nombre' => 'Costo Ventas Nacional', 'tipo' => 'GASTO'],
            ['codigo' => '601705', 'nombre' => 'Remuneraciones (Operaciones)', 'tipo' => 'GASTO'],
            ['codigo' => '601707', 'nombre' => 'Aporte patronal', 'tipo' => 'GASTO'],
            ['codigo' => '603505', 'nombre' => 'Flete maritimo', 'tipo' => 'GASTO'],
            ['codigo' => '603507', 'nombre' => 'Flete aereo-terrestre', 'tipo' => 'GASTO'],
            ['codigo' => '605305', 'nombre' => 'Reparacion y Mantencion', 'tipo' => 'GASTO'],
            ['codigo' => '605405', 'nombre' => 'Aseo y Ornato', 'tipo' => 'GASTO'],
            ['codigo' => '606405', 'nombre' => 'Gastos de viaje', 'tipo' => 'GASTO'],
            ['codigo' => '606406', 'nombre' => 'Viatico', 'tipo' => 'GASTO'],
            ['codigo' => '606705', 'nombre' => 'Gastos telefonia fija', 'tipo' => 'GASTO'],
            ['codigo' => '606710', 'nombre' => 'Gastos telefonia movil', 'tipo' => 'GASTO'],
            ['codigo' => '606730', 'nombre' => 'Insumos de oficina', 'tipo' => 'GASTO'],
            ['codigo' => '607125', 'nombre' => 'Electricidad', 'tipo' => 'GASTO'],
            ['codigo' => '607130', 'nombre' => 'Agua', 'tipo' => 'GASTO'],
            ['codigo' => '608010', 'nombre' => 'Gastos TI asignados', 'tipo' => 'GASTO'],
            ['codigo' => '905510', 'nombre' => 'Gastos bancarios', 'tipo' => 'GASTO'],
            ['codigo' => '905530', 'nombre' => 'Commission garantias', 'tipo' => 'GASTO'],
            ['codigo' => '905805', 'nombre' => 'Perdida tipo de cambio', 'tipo' => 'GASTO'],
            ['codigo' => '999999', 'nombre' => 'Cancelaciones / Ajustes Finales', 'tipo' => 'GASTO'],
        ];

        DB::table('catalogo_plan_maestro')->truncate();

        foreach ($cuentas as $cuenta) {
            DB::table('catalogo_plan_maestro')->insert(array_merge($cuenta, [
                'nivel' => 1,
                'imputable' => true,
            ]));
        }
    }
}