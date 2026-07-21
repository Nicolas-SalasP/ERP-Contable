<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag de intencion de publicacion (opt-in, default false): el producto no se refleja en
 * ningun consumidor externo (Tenri-Web-Page, WordPress, etc.) hasta que se marca explicitamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->boolean('visible_web')->default(false)->after('activo');
            $table->index(['empresa_id', 'visible_web'], 'inventario_productos_empresa_visible_web_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventario_productos', function (Blueprint $table) {
            $table->dropIndex('inventario_productos_empresa_visible_web_idx');
            $table->dropColumn('visible_web');
        });
    }
};
