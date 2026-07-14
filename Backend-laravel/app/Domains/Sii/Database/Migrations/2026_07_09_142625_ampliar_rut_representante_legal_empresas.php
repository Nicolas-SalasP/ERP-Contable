<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * rut_representante_legal era varchar(10) -- alcanza para "12345678-5" sin
 * puntos pero el frontend siempre envia el formato con puntos ("12.345.678-5",
 * 12 caracteres), asi que ningun RUT real de 8 digitos se podia guardar.
 * MySQL exige ALTER explicito; SQLite no aplica largo de VARCHAR en tiempo
 * de escritura, asi que ahi no hace falta tocar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE empresas MODIFY COLUMN rut_representante_legal VARCHAR(20) NULL');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE empresas MODIFY COLUMN rut_representante_legal VARCHAR(10) NULL');
        }
    }
};
