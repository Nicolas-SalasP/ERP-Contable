<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Migración aditiva: agrega jerarquia a roles
|--------------------------------------------------------------------------
|
| Reemplaza la edición directa que NSalas hizo sobre
| 2026_04_23_100001_create_roles_table.php
|
| El campo 'permisos' (json) ya fue agregado en
| 2026_04_28_233346_add_permisos_to_roles_table.php
| así que aquí solo agregamos 'jerarquia'.
|
| Default: 10 (rol básico). Super Admin debería tener 100, Admin 80,
| Contador/Auditor 50. Ver RolSeeder.
|
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'jerarquia')) {
                $table->integer('jerarquia')->default(10)->after('nombre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'jerarquia')) {
                $table->dropColumn('jerarquia');
            }
        });
    }
};
