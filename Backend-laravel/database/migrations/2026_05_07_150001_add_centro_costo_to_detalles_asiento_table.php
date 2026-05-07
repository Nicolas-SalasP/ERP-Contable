<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Migración aditiva: mueve centro_costo del header al detalle (parte 2/2)
|--------------------------------------------------------------------------
|
| Reemplaza la edición directa que NSalas hizo sobre
| 2026_04_23_100020_create_detalles_asiento_table.php
|
| Si en producción quieres preservar los valores existentes que estaban en
| asientos_contables.centro_costo_id, después de correr esta migración corre:
|
|   UPDATE detalles_asiento d
|   INNER JOIN asientos_contables a ON d.asiento_id = a.id
|   SET d.centro_costo_id = a.centro_costo_id,
|       d.empleado_nombre = a.empleado_nombre
|   WHERE a.centro_costo_id IS NOT NULL OR a.empleado_nombre IS NOT NULL;
|
| Y RECIÉN DESPUÉS corre 2026_05_07_140001 que las elimina del header.
|
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalles_asiento', function (Blueprint $table) {
            if (!Schema::hasColumn('detalles_asiento', 'centro_costo_id')) {
                $table->foreignId('centro_costo_id')
                    ->nullable()
                    ->after('descripcion_extensa')
                    ->constrained('centros_costo')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('detalles_asiento', 'empleado_nombre')) {
                $table->string('empleado_nombre', 150)
                    ->nullable()
                    ->after('centro_costo_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalles_asiento', function (Blueprint $table) {
            if (Schema::hasColumn('detalles_asiento', 'centro_costo_id')) {
                try {
                    $table->dropForeign(['centro_costo_id']);
                } catch (\Throwable $e) {
                    // FK puede no existir si la migración fue parcial
                }
                $table->dropColumn('centro_costo_id');
            }

            if (Schema::hasColumn('detalles_asiento', 'empleado_nombre')) {
                $table->dropColumn('empleado_nombre');
            }
        });
    }
};
