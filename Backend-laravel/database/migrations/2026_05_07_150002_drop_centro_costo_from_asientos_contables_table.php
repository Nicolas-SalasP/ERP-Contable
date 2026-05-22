<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Migración aditiva: mueve centro_costo del header al detalle (parte 1/2)
|--------------------------------------------------------------------------
|
| Reemplaza la edición directa que NSalas hizo sobre
| 2026_04_23_100019_create_asientos_contables_table.php
|
| Antes: asientos_contables tenía centro_costo_id y empleado_nombre
|        (un solo CC para todo el asiento).
| Ahora: cada DetalleAsiento puede tener su propio CC y empleado
|        (más flexible y correcto contablemente).
|
| Esta migración solo elimina las columnas del header. La parte 2
| (add_centro_costo_to_detalles_asiento) las agrega al detalle.
|
| OJO con el orden: si hay datos en producción/staging que quieras preservar,
| corre primero la parte 2, copia los valores con un seeder ad-hoc desde
| asiento → detalles, y recién después elimina las columnas aquí.
|
*/
return new class extends Migration {
    public function up(): void
    {
        // Eliminar foreign key primero (Laravel necesita el nombre del FK explícito en algunos casos)
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (Schema::hasColumn('asientos_contables', 'centro_costo_id')) {
                // Best-effort: si el FK fue creado por constrained() su nombre será asientos_contables_centro_costo_id_foreign
                try {
                    $table->dropForeign(['centro_costo_id']);
                } catch (\Throwable $e) {
                    // Si no existe FK, seguimos
                }
                $table->dropColumn('centro_costo_id');
            }

            if (Schema::hasColumn('asientos_contables', 'empleado_nombre')) {
                $table->dropColumn('empleado_nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('asientos_contables', function (Blueprint $table) {
            if (!Schema::hasColumn('asientos_contables', 'centro_costo_id')) {
                $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
            }

            if (!Schema::hasColumn('asientos_contables', 'empleado_nombre')) {
                $table->string('empleado_nombre', 150)->nullable();
            }
        });
    }
};
