<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tablas = [
        'facturas',
        'clientes',
        'proveedores',
        'activos_fijos',
        'asientos_contables',
    ];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasTable($tabla) && ! Schema::hasColumn($tabla, 'deleted_at')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'deleted_at')) {
                Schema::table($tabla, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
