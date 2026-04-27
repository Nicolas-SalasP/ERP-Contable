<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Contabilidad\Models\CatalogoPlanMaestro;
use Illuminate\Support\Facades\DB;

class CatalogoPlanMaestroSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la tabla antes de inyectar
        DB::table('catalogo_plan_maestro')->truncate();

        $cuentas = [
            // ==========================================
            // 1. ACTIVOS (Circulantes y Fijos)
            // ==========================================
            ['codigo' => '110101', 'nombre' => 'Caja', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110102', 'nombre' => 'Banco', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110201', 'nombre' => 'Depósitos a Plazo', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110301', 'nombre' => 'Clientes (Deudores por Venta)', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110302', 'nombre' => 'Documentos por Cobrar', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110401', 'nombre' => 'Anticipos al Personal', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110501', 'nombre' => 'Existencias / Mercaderías', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110601', 'nombre' => 'IVA Crédito Fiscal', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '110602', 'nombre' => 'PPM por Recuperar', 'tipo' => 'ACTIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '120101', 'nombre' => 'Terrenos y Bienes Raíces', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => true],
            ['codigo' => '120102', 'nombre' => 'Maquinarias y Equipos', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => true],
            ['codigo' => '120103', 'nombre' => 'Muebles y Útiles', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => true],
            ['codigo' => '120201', 'nombre' => 'Depreciación Acumulada', 'tipo' => 'ACTIVO', 'nivel' => 2, 'imputable' => true],

            // ==========================================
            // 2. PASIVOS (Corto y Largo Plazo)
            // ==========================================
            ['codigo' => '210101', 'nombre' => 'Proveedores', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210102', 'nombre' => 'Acreedores Varios', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210103', 'nombre' => 'Honorarios por Pagar', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210201', 'nombre' => 'IVA Débito Fiscal', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210202', 'nombre' => 'Impuesto Único de los Trabajadores', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210203', 'nombre' => 'Retenciones 2da Categoría (13%)', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210301', 'nombre' => 'Remuneraciones por Pagar', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210302', 'nombre' => 'Instituciones Previsionales por Pagar', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '210401', 'nombre' => 'Obligaciones con Bancos a Corto Plazo', 'tipo' => 'PASIVO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '220101', 'nombre' => 'Obligaciones con Bancos a Largo Plazo', 'tipo' => 'PASIVO', 'nivel' => 2, 'imputable' => true],

            // ==========================================
            // 3. PATRIMONIO
            // ==========================================
            ['codigo' => '310101', 'nombre' => 'Capital Pagado', 'tipo' => 'PATRIMONIO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '310201', 'nombre' => 'Utilidades Retenidas (Acumuladas)', 'tipo' => 'PATRIMONIO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '310202', 'nombre' => 'Pérdidas Acumuladas', 'tipo' => 'PATRIMONIO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '310301', 'nombre' => 'Utilidad o Pérdida del Ejercicio', 'tipo' => 'PATRIMONIO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '310401', 'nombre' => 'Cuenta Obligada Socios', 'tipo' => 'PATRIMONIO', 'nivel' => 1, 'imputable' => true],

            // ==========================================
            // 4. INGRESOS (Resultados Positivos)
            // ==========================================
            ['codigo' => '410101', 'nombre' => 'Ingresos por Ventas del Giro', 'tipo' => 'INGRESO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '410102', 'nombre' => 'Ingresos por Servicios del Giro', 'tipo' => 'INGRESO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '410201', 'nombre' => 'Ingresos Financieros (Intereses)', 'tipo' => 'INGRESO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '410301', 'nombre' => 'Otros Ingresos Fuera de la Explotación', 'tipo' => 'INGRESO', 'nivel' => 1, 'imputable' => true],

            // ==========================================
            // 5. GASTOS Y COSTOS (Resultados Negativos)
            // ==========================================
            ['codigo' => '510101', 'nombre' => 'Costo de Ventas / Gastos Generales', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510201', 'nombre' => 'Remuneraciones (Sueldos)', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510202', 'nombre' => 'Honorarios', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510203', 'nombre' => 'Leyes Sociales (Aporte Patronal)', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510301', 'nombre' => 'Arriendos Pagados', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510302', 'nombre' => 'Gastos de Administración y Ventas', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510401', 'nombre' => 'Depreciación del Ejercicio', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510501', 'nombre' => 'Gastos Financieros e Intereses', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
            ['codigo' => '510601', 'nombre' => 'Castigo de Deudores Incobrables', 'tipo' => 'GASTO', 'nivel' => 1, 'imputable' => true],
        ];

        foreach ($cuentas as $cuenta) {
            CatalogoPlanMaestro::create($cuenta);
        }
    }
}