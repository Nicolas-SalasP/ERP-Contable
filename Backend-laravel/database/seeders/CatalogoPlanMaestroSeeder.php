<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogoPlanMaestroSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('catalogo_plan_maestro')->truncate();
        
        $cuentas = [
            // =================================================================
            // CLASE 1: ACTIVOS
            // =================================================================
            ['codigo' => '1', 'nombre' => 'ACTIVO', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => false],
            ['codigo' => '11', 'nombre' => 'ACTIVO NO CORRIENTE', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '1112', 'nombre' => 'INTANGIBLES', 'tipo' => 'ACTIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '111205', 'nombre' => 'Software', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '111206', 'nombre' => 'Depreciacion Acum. Software', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '1120', 'nombre' => 'PROPIEDADES, PLANTA Y EQUIPO', 'tipo' => 'ACTIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '112005', 'nombre' => 'Edificios', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112006', 'nombre' => 'Depreciacion Acum. Edificios', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112105', 'nombre' => 'Maquinarias y equipos', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112106', 'nombre' => 'Depreciacion Acum. Maquinarias', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112205', 'nombre' => 'Hardware / Equipos Computacionales', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112206', 'nombre' => 'Depreciacion Acum. Hardware', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112210', 'nombre' => 'Vehiculos', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112211', 'nombre' => 'Depreciacion Acum. Vehiculos', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112215', 'nombre' => 'Camiones', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112216', 'nombre' => 'Depreciacion Acum. Camiones', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112220', 'nombre' => 'Muebles e instalaciones', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '112221', 'nombre' => 'Depreciacion Acum. Muebles', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],

            ['codigo' => '15', 'nombre' => 'ACTIVO CORRIENTE', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '1510', 'nombre' => 'INVENTARIOS', 'tipo' => 'ACTIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '151005', 'nombre' => 'Inventario Materiales', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '151010', 'nombre' => 'Inventario Insumos', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '151225', 'nombre' => 'Mercaderia entregada en consignacion', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],

            ['codigo' => '1520', 'nombre' => 'DEUDORES COMERCIALES Y OTROS', 'tipo' => 'ACTIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '152005', 'nombre' => 'Cuentas por Cobrar Clientes', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '152045', 'nombre' => 'Cheques en cartera', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '152406', 'nombre' => 'Fondos por Rendir', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '152408', 'nombre' => 'Canje divisa', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '152540', 'nombre' => 'IVA por cobrar', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '152541', 'nombre' => 'PPM por recuperar', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],

            ['codigo' => '1540', 'nombre' => 'EFECTIVO Y EQUIVALENTES', 'tipo' => 'ACTIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '154020', 'nombre' => 'Caja Chica', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '154205', 'nombre' => 'Banco Santander CLP', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '154230', 'nombre' => 'Banco Estado CLP', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '154236', 'nombre' => 'Banco Itau CLP', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '154240', 'nombre' => 'Banco Security CLP', 'tipo' => 'ACTIVO', 'nivel' => 4, 'imputable' => true],

            // =================================================================
            // CLASE 3: PASIVO Y PATRIMONIO
            // =================================================================
            ['codigo' => '3', 'nombre' => 'PASIVO Y PATRIMONIO', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => false],
            ['codigo' => '31', 'nombre' => 'PATRIMONIO', 'tipo' => 'PATRIMONIO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '311005', 'nombre' => 'Resultado Acumulado', 'tipo' => 'PATRIMONIO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '311406', 'nombre' => 'Correccion Monetaria Patrimonio', 'tipo' => 'PATRIMONIO', 'nivel' => 3, 'imputable' => true],

            ['codigo' => '33', 'nombre' => 'PASIVO NO CORRIENTE', 'tipo' => 'PASIVO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '331005', 'nombre' => 'Provision Impuesto a la Renta', 'tipo' => 'PASIVO', 'nivel' => 3, 'imputable' => true],

            ['codigo' => '35', 'nombre' => 'PASIVO CORRIENTE', 'tipo' => 'PASIVO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '3520', 'nombre' => 'CUENTAS POR PAGAR COMERCIALES', 'tipo' => 'PASIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '352050', 'nombre' => 'Cheques por pagar', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '352060', 'nombre' => 'Tarjetas de credito', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '352075', 'nombre' => 'Linea sobregiro', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '352105', 'nombre' => 'Cuentas por pagar', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '352130', 'nombre' => 'Facturas por pagar (Puente)', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '352135', 'nombre' => 'Facturas por pagar Honorarios', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],

            ['codigo' => '3532', 'nombre' => 'OBLIGACIONES TRIBUTARIAS Y SOCIALES', 'tipo' => 'PASIVO', 'nivel' => 3, 'imputable' => false],
            ['codigo' => '353205', 'nombre' => 'Remuneraciones por pagar', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '353242', 'nombre' => 'Impuesto Unico Trabajadores', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '353245', 'nombre' => 'Retenciones AFP', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '353350', 'nombre' => 'IVA por Cobrar (Transito)', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '353360', 'nombre' => 'IVA por pagar', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],
            ['codigo' => '353410', 'nombre' => 'Honorarios devengados por pagar', 'tipo' => 'PASIVO', 'nivel' => 4, 'imputable' => true],

            // =================================================================
            // CLASE 5: INGRESOS
            // =================================================================
            ['codigo' => '5', 'nombre' => 'INGRESOS', 'tipo' => 'INGRESO', 'nivel' => 1, 'imputable' => false],
            ['codigo' => '50', 'nombre' => 'INGRESOS OPERACIONALES', 'tipo' => 'INGRESO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '501105', 'nombre' => 'Ventas Nacionales', 'tipo' => 'INGRESO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '501110', 'nombre' => 'Ventas Servicios', 'tipo' => 'INGRESO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '501205', 'nombre' => 'Ventas - Exportacion', 'tipo' => 'INGRESO', 'nivel' => 3, 'imputable' => true],

            // =================================================================
            // CLASE 6 Y 9: GASTOS
            // =================================================================
            ['codigo' => '6', 'nombre' => 'GASTOS OPERACIONALES', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => false],
            ['codigo' => '609', 'nombre' => 'DEPRECIACIONES Y AMORTIZACIONES', 'tipo' => 'GASTO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '609101', 'nombre' => 'Gasto Depreciacion Edificios', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609102', 'nombre' => 'Gasto Depreciacion Maquinarias', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609103', 'nombre' => 'Gasto Depreciacion Hardware', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609104', 'nombre' => 'Gasto Depreciacion Vehiculos', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609105', 'nombre' => 'Gasto Depreciacion Camiones', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609106', 'nombre' => 'Gasto Depreciacion Muebles', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '609107', 'nombre' => 'Gasto Amortizacion Software', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],

            ['codigo' => '60', 'nombre' => 'ADMINISTRACION Y VENTAS', 'tipo' => 'GASTO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '601205', 'nombre' => 'Costo Ventas Nacional', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '601705', 'nombre' => 'Remuneraciones (Operaciones)', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '601707', 'nombre' => 'Aporte patronal', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '603505', 'nombre' => 'Flete maritimo', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '603507', 'nombre' => 'Flete aereo-terrestre', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '605305', 'nombre' => 'Reparacion y Mantencion', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '605405', 'nombre' => 'Aseo y Ornato', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '606405', 'nombre' => 'Gastos de viaje', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '606406', 'nombre' => 'Viatico', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '606705', 'nombre' => 'Gastos telefonia fija', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '606710', 'nombre' => 'Gastos telefonia movil', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '606730', 'nombre' => 'Insumos de oficina', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '607125', 'nombre' => 'Electricidad', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '607130', 'nombre' => 'Agua', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '608010', 'nombre' => 'Gastos TI asignados', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],

            ['codigo' => '9', 'nombre' => 'GASTOS NO OPERACIONALES', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => false],
            ['codigo' => '90', 'nombre' => 'GASTOS FINANCIEROS Y OTROS', 'tipo' => 'GASTO', 'nivel' => 2, 'imputable' => false],
            ['codigo' => '905510', 'nombre' => 'Gastos bancarios', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '905530', 'nombre' => 'Commission garantias', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '905805', 'nombre' => 'Perdida tipo de cambio', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
            ['codigo' => '999999', 'nombre' => 'Cancelaciones / Ajustes Finales', 'tipo' => 'GASTO', 'nivel' => 3, 'imputable' => true],
        ];

        DB::table('catalogo_plan_maestro')->insert($cuentas);
    }
}